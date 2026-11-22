<?php
/**
 * CampusFix - Administrator Master Ticket Control & Inspection
 * Full oversight of ticket details, status overrides, internal audit notes, and communication logs.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/PaymentService.php';

require_role('admin');

$user = current_user();
$ticketId = (int) ($_GET['id'] ?? 0);

if ($ticketId <= 0) {
    flash_message('danger', 'Invalid ticket identifier.');
    header("Location: " . BASE_URL . "admin/all-tickets.php");
    exit;
}

$pdo = get_db_connection();
$payment = PaymentService::getPaymentByTicket($ticketId);
$currency = PaymentService::getCurrencySymbol();

// 1. Fetch Ticket
$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name, c.icon as category_icon, c.department_in_charge,
           l.building_name, l.building_code, l.floor, l.room_number, l.area_description,
           r.full_name as reporter_name, r.email as reporter_email, r.phone as reporter_phone, r.department as reporter_dept,
           tech.full_name as technician_name, tech.email as technician_email, tech.phone as technician_phone
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

// 2. Handle Admin Status / Priority Overrides or Comments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
    $action = $_POST['action'] ?? '';

    // Override Status
    if ($action === 'override_status') {
        $newStatus = trim($_POST['status'] ?? $ticket['status']);
        $reason    = trim($_POST['reason'] ?? 'Admin status override.');

        $upStmt = $pdo->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?");
        $upStmt->execute([$newStatus, $ticketId]);

        // Status History
        $histStmt = $pdo->prepare("
            INSERT INTO ticket_status_history (ticket_id, old_status, new_status, changed_by, notes, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $histStmt->execute([$ticketId, $ticket['status'], $newStatus, $user['id'], "Admin override: {$reason}"]);

        log_activity($user['id'], 'admin_status_override', 'ticket', $ticketId, ['new_status' => $newStatus]);
        flash_message('success', "Ticket status changed to " . ucfirst($newStatus));
        header("Location: " . BASE_URL . "admin/ticket-details.php?id=" . $ticketId);
        exit;
    }

    // Add Internal / Broadcast Note
    if ($action === 'add_admin_comment') {
        $commentText = trim($_POST['comment'] ?? '');
        $isInternal  = isset($_POST['is_internal']) ? 1 : 0;

        if (!empty($commentText)) {
            $cStmt = $pdo->prepare("
                INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $cStmt->execute([$ticketId, $user['id'], $commentText, $isInternal]);

            log_activity($user['id'], 'admin_comment_added', 'ticket', $ticketId, ['is_internal' => $isInternal]);
            flash_message('success', $isInternal ? 'Internal admin note recorded.' : 'Public message sent to reporter & technician.');
            header("Location: " . BASE_URL . "admin/ticket-details.php?id=" . $ticketId);
            exit;
        }
    }

    // Attach Billable Surcharge
    if ($action === 'attach_surcharge') {
        $amount = max(0.01, (float) ($_POST['billing_amount'] ?? 0));
        $desc = trim($_POST['billing_desc'] ?? 'Material and repair surcharge');
        PaymentService::attachSurcharge($ticketId, $amount, $desc, $user['id']);
        flash_message('success', "Maintenance surcharge of {$currency} " . number_format($amount, 2) . " attached. Student notified.");
        header("Location: " . BASE_URL . "admin/ticket-details.php?id=" . $ticketId);
        exit;
    }

    // Waive Surcharge
    if ($action === 'waive_surcharge') {
        $reason = trim($_POST['waive_reason'] ?? 'Waived by administrative decision.');
        PaymentService::waiveSurcharge($ticketId, $user['id'], $reason);
        flash_message('info', 'Maintenance surcharge waived successfully.');
        header("Location: " . BASE_URL . "admin/ticket-details.php?id=" . $ticketId);
        exit;
    }
}

// Fetch images, status history, and comments
$photos = $pdo->prepare("SELECT * FROM ticket_images WHERE ticket_id = ? ORDER BY id ASC");
$photos->execute([$ticketId]);
$ticketImages = $photos->fetchAll();

$hist = $pdo->prepare("
    SELECT h.*, u.full_name as author_name, u.role as author_role
    FROM ticket_status_history h
    JOIN users u ON h.changed_by = u.id
    WHERE h.ticket_id = ?
    ORDER BY h.created_at ASC
");
$hist->execute([$ticketId]);
$statusHistory = $hist->fetchAll();

$comm = $pdo->prepare("
    SELECT c.*, u.full_name, u.role, u.department
    FROM ticket_comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.ticket_id = ?
    ORDER BY c.created_at ASC
");
$comm->execute([$ticketId]);
$allComments = $comm->fetchAll();

$pageTitle = "Admin Ticket: {$ticket['ticket_number']}";
$pageHeading = "Master Incident: {$ticket['ticket_number']}";
$pageSubtitle = "Complete administrative oversight, lifecycle status controls, and audit records";

include_once INCLUDES_PATH . 'header.php';
include_once INCLUDES_PATH . 'sidebar.php';
?>

<div class="d-flex flex-column flex-1 w-100">
    <?php include_once INCLUDES_PATH . 'navbar.php'; ?>

    <div class="container-fluid px-0">

        <!-- Top Header Card -->
        <div class="cf-card mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="p-3 rounded-3 bg-light border text-primary fs-3">
                        <i class="bi <?= htmlspecialchars($ticket['category_icon']) ?>"></i>
                    </span>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h4 class="fw-bold text-dark font-monospace mb-0"><?= htmlspecialchars($ticket['ticket_number']) ?></h4>
                            <?= render_status_badge($ticket['status']) ?>
                            <?= render_priority_badge($ticket['priority']) ?>
                        </div>
                        <h6 class="fw-semibold text-secondary mt-1 mb-0"><?= htmlspecialchars($ticket['title']) ?></h6>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= BASE_URL ?>admin/assign-ticket.php?id=<?= $ticket['id'] ?>" class="btn btn-primary fw-semibold btn-sm shadow-sm px-3">
                        <i class="bi bi-person-gear me-1"></i> Reassign / Triage
                    </a>
                    <button type="button" class="btn btn-outline-danger fw-semibold btn-sm" data-bs-toggle="modal" data-bs-target="#statusModal">
                        <i class="bi bi-arrow-repeat me-1"></i> Override Status
                    </button>
                    <a href="<?= BASE_URL ?>admin/all-tickets.php" class="btn btn-light border btn-sm text-muted">
                        <i class="bi bi-arrow-left me-1"></i> Back to Ledger
                    </a>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left: Description, Images, Discussion -->
            <div class="col-lg-8">

                <!-- Problem Description -->
                <div class="cf-card mb-4">
                    <h5 class="cf-card-title mb-3">Fault Details</h5>
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <p class="mb-0 text-dark" style="white-space: pre-line; line-height: 1.6;"><?= htmlspecialchars($ticket['description']) ?></p>
                    </div>

                    <div class="row g-3 small">
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Category</span>
                            <strong class="text-dark"><?= htmlspecialchars($ticket['category_name']) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Department in Charge</span>
                            <strong class="text-dark"><?= htmlspecialchars($ticket['department_in_charge']) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Reported Severity</span>
                            <?= render_severity_badge($ticket['severity']) ?>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Admin Priority</span>
                            <?= render_priority_badge($ticket['priority']) ?>
                        </div>
                    </div>
                </div>

                <!-- Photos -->
                <div class="cf-card mb-4">
                    <h5 class="cf-card-title mb-3">Photographic Attachments (<?= count($ticketImages) ?>)</h5>
                    <?php if (empty($ticketImages)): ?>
                        <p class="text-muted small fst-italic mb-0">No images attached.</p>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($ticketImages as $img): ?>
                                <div class="col-sm-4">
                                    <div class="border rounded-3 overflow-hidden shadow-sm position-relative">
                                        <a href="<?= BASE_URL . htmlspecialchars($img['file_path']) ?>" target="_blank" data-bs-toggle="tooltip" title="Click to view full image">
                                            <img src="<?= BASE_URL . htmlspecialchars($img['file_path']) ?>" class="w-100" style="height: 140px; object-fit: cover;" alt="Evidence">
                                        </a>
                                        <div class="p-1 px-2 bg-dark text-white opacity-75 small d-flex justify-content-between" style="font-size: 0.7rem;">
                                            <span class="text-truncate"><?= htmlspecialchars($img['file_name']) ?></span>
                                            <span class="badge bg-secondary"><?= $img['image_type'] ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Communications & Notes -->
                <div class="cf-card mb-4">
                    <h5 class="cf-card-title mb-3">Communication & Investigation Notes</h5>

                    <?php if (empty($allComments)): ?>
                        <p class="text-muted small fst-italic mb-3">No messages logged.</p>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3 mb-4">
                            <?php foreach ($allComments as $c): ?>
                                <div class="p-3 rounded-3 border <?= ($c['is_internal']) ? 'bg-warning-subtle border-warning' : 'bg-light' ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold small text-dark">
                                            <?= htmlspecialchars($c['full_name']) ?>
                                            <?php if ($c['is_internal']): ?>
                                                <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">INTERNAL STAFF NOTE</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary ms-1" style="font-size: 0.65rem; text-transform: uppercase;"><?= htmlspecialchars($c['role']) ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <small class="text-muted" style="font-size: 0.75rem;"><?= time_ago($c['created_at']) ?></small>
                                    </div>
                                    <p class="mb-0 small text-dark" style="white-space: pre-line;"><?= htmlspecialchars($c['comment']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="p-3 bg-light rounded-3 border">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_admin_comment">
                        <label class="form-label small fw-semibold text-secondary mb-1">Add Note / Message</label>
                        <textarea class="form-control mb-2" name="comment" rows="2" placeholder="Record administrator observation or broadcast clarification..." required></textarea>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_internal" id="is_internal" value="1" checked>
                                <label class="form-check-label small text-secondary" for="is_internal">
                                    <i class="bi bi-lock-fill text-warning"></i> Internal staff note only
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm fw-semibold px-3">Post Note</button>
                        </div>
                    </form>
                </div>

            </div>

            <!-- Right: People, Location & Status History -->
            <div class="col-lg-4">

                <!-- Maintenance Surcharge & Financial Billing Card -->
                <div class="cf-card mb-4">
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="bi bi-wallet2 text-primary me-1"></i> Maintenance Surcharge
                        </h6>
                        <span class="badge bg-primary-subtle text-primary border border-primary small">Paystack Sandbox</span>
                    </div>

                    <?php if (!empty($ticket['payment_status']) && $ticket['payment_status'] === 'paid'): ?>
                        <div class="p-3 bg-success-subtle text-success border border-success rounded-3 mb-3">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-check-circle-fill fs-5"></i>
                                <strong class="fs-6"><?= $currency ?> <?= number_format($ticket['billing_amount'], 2) ?> Paid</strong>
                            </div>
                            <small class="d-block text-dark mb-2">
                                <?= htmlspecialchars($ticket['billing_description'] ?: 'Material Surcharge') ?>
                            </small>
                            <?php if ($payment): ?>
                                <small class="text-muted d-block">Method: <strong><?= htmlspecialchars($payment['payment_channel'] ?: 'Mobile Money') ?></strong></small>
                                <small class="text-muted d-block font-monospace">Ref: <?= htmlspecialchars($payment['payment_reference']) ?></small>
                                <a href="<?= BASE_URL ?>student/receipt.php?ref=<?= urlencode($payment['receipt_number']) ?>" target="_blank" class="btn btn-sm btn-success w-100 fw-semibold mt-2">
                                    <i class="bi bi-receipt me-1"></i> View Official Receipt
                                </a>
                            <?php endif; ?>
                        </div>

                    <?php elseif (!empty($ticket['payment_status']) && $ticket['payment_status'] === 'pending'): ?>
                        <div class="p-3 bg-warning-subtle text-warning-emphasis border border-warning rounded-3 mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <strong class="fs-6 text-dark"><?= $currency ?> <?= number_format($ticket['billing_amount'], 2) ?></strong>
                                <span class="badge bg-warning text-dark text-uppercase small">Pending</span>
                            </div>
                            <small class="d-block text-secondary mb-2">
                                <?= htmlspecialchars($ticket['billing_description'] ?: 'Material Surcharge') ?>
                            </small>
                            <form method="POST" action="" onsubmit="return confirm('Waive this maintenance surcharge?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="waive_surcharge">
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100 fw-semibold">
                                    <i class="bi bi-slash-circle me-1"></i> Waive Surcharge
                                </button>
                            </form>
                        </div>

                    <?php elseif (!empty($ticket['payment_status']) && $ticket['payment_status'] === 'waived'): ?>
                        <div class="p-2 bg-light border rounded text-center small text-muted mb-3">
                            <i class="bi bi-info-circle me-1"></i> Surcharge was waived by administration.
                        </div>
                    <?php else: ?>
                        <div class="p-2 bg-light border rounded text-center small text-muted mb-3">
                            No billing surcharge attached to this incident.
                        </div>
                    <?php endif; ?>

                    <!-- Attach Surcharge Form Toggle -->
                    <?php if (empty($ticket['payment_status']) || $ticket['payment_status'] === 'not_required' || $ticket['payment_status'] === 'waived'): ?>
                        <button class="btn btn-outline-primary btn-sm w-100 fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#attachSurchargeBox">
                            <i class="bi bi-plus-circle me-1"></i> Attach Material Surcharge
                        </button>
                        <div class="collapse mt-3" id="attachSurchargeBox">
                            <form method="POST" action="" class="p-3 bg-light border rounded-3">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="attach_surcharge">

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold text-secondary">Amount (<?= $currency ?>) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.50" min="1" name="billing_amount" class="form-control form-control-sm" placeholder="e.g. 50.00" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-secondary">Reason / Part Description <span class="text-danger">*</span></label>
                                    <input type="text" name="billing_desc" class="form-control form-control-sm" placeholder="e.g. Replacement HDMI Cable & Adapter" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">
                                    <i class="bi bi-save me-1"></i> Save & Alert Student
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Assigned Technician -->
                <div class="cf-card mb-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-person-badge-fill text-primary me-1"></i> Assigned Technician
                    </h6>
                    <?php if (!empty($ticket['technician_name'])): ?>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 44px; height: 44px;">
                                <?= strtoupper(substr($ticket['technician_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($ticket['technician_name']) ?></h6>
                                <small class="text-muted d-block"><?= htmlspecialchars($ticket['technician_email']) ?></small>
                                <small class="text-primary font-monospace"><?= htmlspecialchars($ticket['technician_phone'] ?? 'No phone') ?></small>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="p-3 bg-light rounded text-center small text-muted mb-2">
                            Awaiting Technician Assignment
                        </div>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>admin/assign-ticket.php?id=<?= $ticket['id'] ?>" class="btn btn-outline-primary btn-sm w-100 fw-semibold">
                        <i class="bi bi-person-gear me-1"></i> Change Assignment
                    </a>
                </div>

                <!-- Reporter Card -->
                <div class="cf-card mb-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-person-circle text-primary me-1"></i> Student / Reporter
                    </h6>
                    <div class="small text-secondary">
                        <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($ticket['reporter_name']) ?></div>
                        <div><?= htmlspecialchars($ticket['reporter_dept'] ?? 'Student') ?></div>
                        <div class="mt-2"><i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($ticket['reporter_email']) ?></div>
                        <div><i class="bi bi-telephone me-1"></i> <?= htmlspecialchars($ticket['reporter_phone'] ?? 'None') ?></div>
                    </div>
                </div>

                <!-- Location Card -->
                <div class="cf-card mb-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-geo-alt-fill text-danger me-1"></i> Location Details
                    </h6>
                    <div class="small">
                        <div class="text-muted">Building:</div>
                        <strong class="text-dark"><?= htmlspecialchars($ticket['building_name']) ?> (<?= htmlspecialchars($ticket['building_code']) ?>)</strong>
                        <div class="text-muted mt-2">Room:</div>
                        <span class="text-dark"><?= htmlspecialchars($ticket['floor']) ?> — <?= htmlspecialchars($ticket['room_number'] ?? 'General') ?></span>
                    </div>
                </div>

                <!-- Status History Timeline -->
                <div class="cf-card mb-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-clock-history text-secondary me-1"></i> Lifecycle Audit History
                    </h6>
                    <div class="timeline ps-3 border-start border-2 border-primary-subtle position-relative ms-2">
                        <?php foreach ($statusHistory as $h): ?>
                            <div class="position-relative mb-3 ps-3">
                                <span class="position-absolute start-0 top-0 translate-middle p-1 bg-primary border border-light rounded-circle" style="left: -1px !important;"></span>
                                <div class="fw-semibold text-dark small" style="text-transform: capitalize;">
                                    Status: <code><?= htmlspecialchars($h['new_status']) ?></code>
                                </div>
                                <div class="text-muted" style="font-size: 0.725rem;">
                                    by <?= htmlspecialchars($h['author_name']) ?> • <?= time_ago($h['created_at']) ?>
                                </div>
                                <?php if (!empty($h['notes'])): ?>
                                    <div class="text-secondary small fst-italic mt-1" style="font-size: 0.8rem;">
                                        "<?= htmlspecialchars($h['notes']) ?>"
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- Modal: Override Status -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="override_status">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-arrow-repeat"></i> Administrative Status Override</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="status" class="form-label small fw-bold text-dark">Target Status</label>
                        <select class="form-select" name="status" id="status" required>
                            <option value="pending" <?= ($ticket['status'] === 'pending') ? 'selected' : '' ?>>Pending Triage</option>
                            <option value="assigned" <?= ($ticket['status'] === 'assigned') ? 'selected' : '' ?>>Assigned</option>
                            <option value="in_progress" <?= ($ticket['status'] === 'in_progress') ? 'selected' : '' ?>>In Progress</option>
                            <option value="resolved" <?= ($ticket['status'] === 'resolved') ? 'selected' : '' ?>>Resolved</option>
                            <option value="closed" <?= ($ticket['status'] === 'closed') ? 'selected' : '' ?>>Closed</option>
                            <option value="reopened" <?= ($ticket['status'] === 'reopened') ? 'selected' : '' ?>>Reopened</option>
                            <option value="cancelled" <?= ($ticket['status'] === 'cancelled') ? 'selected' : '' ?>>Cancelled / Duplicate</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="reason" class="form-label small fw-bold text-dark">Reason / Administrative Notes</label>
                        <textarea class="form-control" name="reason" id="reason" rows="3" placeholder="Explain the justification for this administrative state override..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-semibold px-3">Execute Override</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once INCLUDES_PATH . 'footer.php'; ?>

