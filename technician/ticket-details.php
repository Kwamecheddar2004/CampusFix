<?php
/**
 * CampusFix - Technician Work Order Inspection & Action View
 * Allows technicians to start work, document investigation notes, upload resolution proof, and mark resolved.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/UploadService.php';
require_once __DIR__ . '/../includes/PaymentService.php';

require_role('technician');

$user = current_user();
$ticketId = (int) ($_GET['id'] ?? 0);

if ($ticketId <= 0) {
    flash_message('danger', 'Invalid ticket ID.');
    header("Location: " . BASE_URL . "technician/dashboard.php");
    exit;
}

$pdo = get_db_connection();
$payment = PaymentService::getPaymentByTicket($ticketId);
$currency = PaymentService::getCurrencySymbol();

// 1. Fetch Ticket Record
$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name, c.icon as category_icon, c.department_in_charge,
           l.building_name, l.building_code, l.floor, l.room_number, l.area_description,
           r.full_name as reporter_name, r.email as reporter_email, r.phone as reporter_phone, r.department as reporter_dept
    FROM tickets t
    JOIN categories c ON t.category_id = c.id
    JOIN campus_locations l ON t.location_id = l.id
    JOIN users r ON t.reporter_id = r.id
    WHERE t.id = ?
    LIMIT 1
");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    flash_message('danger', 'Work order not found.');
    header("Location: " . BASE_URL . "technician/dashboard.php");
    exit;
}

// Security: Verify technician is assigned to this ticket
if ((int) $ticket['assigned_to'] !== (int) $user['id']) {
    flash_message('danger', 'This ticket is not assigned to your technician profile.');
    header("Location: " . BASE_URL . "technician/dashboard.php");
    exit;
}

// 2. Handle Technician Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    enforce_csrf();
    $action = $_POST['action'];

    // Action A: Start Work (Assigned -> In Progress)
    if ($action === 'start_work') {
        $updateStmt = $pdo->prepare("UPDATE tickets SET status = 'in_progress', updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$ticketId]);

        // Status History
        $histStmt = $pdo->prepare("
            INSERT INTO ticket_status_history (ticket_id, old_status, new_status, changed_by, notes, created_at)
            VALUES (?, ?, 'in_progress', ?, 'Technician commenced physical diagnostics and repair work.', NOW())
        ");
        $histStmt->execute([$ticketId, $ticket['status'], $user['id']]);

        // In-App Notification to Reporter
        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, ticket_id, title, message, channel, status, created_at)
            VALUES (?, ?, ?, ?, 'in_app', 'sent', NOW())
        ");
        $notifStmt->execute([
            $ticket['reporter_id'],
            $ticketId,
            "Repair Started ({$ticket['ticket_number']})",
            "Technician {$user['full_name']} has started work on your reported issue."
        ]);

        log_activity($user['id'], 'ticket_started', 'ticket', $ticketId);
        flash_message('success', 'Work order status updated to In Progress.');
        header("Location: " . BASE_URL . "technician/ticket-details.php?id=" . $ticketId);
        exit;
    }

    // Action B: Mark Resolved (In Progress -> Resolved)
    if ($action === 'mark_resolved') {
        $resolutionNotes = trim($_POST['resolution_notes'] ?? '');
        $partsReplaced   = trim($_POST['parts_replaced'] ?? '');

        if (empty($resolutionNotes)) {
            flash_message('danger', 'Please document the repair actions taken in the resolution notes.');
        } else {
            $fullNotes = $resolutionNotes;
            if (!empty($partsReplaced)) {
                $fullNotes .= "\n[Replacement Parts / Materials: " . $partsReplaced . "]";
            }

            // Handle optional after-repair verification photo
            $resolutionPhotos = [];
            if (!empty($_FILES['resolution_photos']['name'][0])) {
                try {
                    $resolutionPhotos = UploadService::handleUploads($_FILES['resolution_photos'], TICKET_UPLOADS_PATH, 'uploads/tickets/', 2);
                } catch (Exception $e) {
                    flash_message('warning', 'Photo upload failed: ' . $e->getMessage());
                }
            }

            $pdo->beginTransaction();

            // Update ticket
            $resStmt = $pdo->prepare("
                UPDATE tickets 
                SET status = 'resolved', resolution_notes = ?, resolved_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $resStmt->execute([$fullNotes, $ticketId]);

            // Save resolution images if any
            if (!empty($resolutionPhotos)) {
                UploadService::saveTicketImages($pdo, $ticketId, $user['id'], $resolutionPhotos, 'resolution');
            }

            // Status history
            $histStmt = $pdo->prepare("
                INSERT INTO ticket_status_history (ticket_id, old_status, new_status, changed_by, notes, created_at)
                VALUES (?, ?, 'resolved', ?, ?, NOW())
            ");
            $histStmt->execute([$ticketId, $ticket['status'], $user['id'], "Technician completed repair: " . substr($resolutionNotes, 0, 150)]);

            // Alert student
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, ticket_id, title, message, channel, status, created_at)
                VALUES (?, ?, ?, ?, 'in_app', 'sent', NOW())
            ");
            $notifStmt->execute([
                $ticket['reporter_id'],
                $ticketId,
                "Fault Resolved ({$ticket['ticket_number']})",
                "Technician {$user['full_name']} marked your ticket as Resolved. Please inspect and confirm resolution."
            ]);

            log_activity($user['id'], 'ticket_resolved', 'ticket', $ticketId, ['notes' => $fullNotes]);
            $pdo->commit();

            flash_message('success', 'Ticket successfully marked as Resolved! The student has been notified to verify.');
            header("Location: " . BASE_URL . "technician/ticket-details.php?id=" . $ticketId);
            exit;
        }
    }

    // Action C: Add Staff Internal Note or Public Message
    if ($action === 'add_note') {
        $noteText = trim($_POST['note_text'] ?? '');
        $isInternal = isset($_POST['is_internal']) ? 1 : 0;

        if (!empty($noteText)) {
            $nStmt = $pdo->prepare("
                INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $nStmt->execute([$ticketId, $user['id'], $noteText, $isInternal]);

            log_activity($user['id'], 'comment_added', 'ticket', $ticketId, ['is_internal' => $isInternal]);
            flash_message('success', $isInternal ? 'Internal technical note added.' : 'Public reply posted to student.');
            header("Location: " . BASE_URL . "technician/ticket-details.php?id=" . $ticketId);
            exit;
        }
    }
}

// Fetch photos, status history, and comments (including internal notes)
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

// Fetch admin assignment instructions
$assignStmt = $pdo->prepare("SELECT * FROM ticket_assignments WHERE ticket_id = ? AND technician_id = ? ORDER BY id DESC LIMIT 1");
$assignStmt->execute([$ticketId, $user['id']]);
$assignmentInfo = $assignStmt->fetch();

$pageTitle = "Job: {$ticket['ticket_number']}";
$pageHeading = "Work Order {$ticket['ticket_number']}";
$pageSubtitle = "Fault inspection, diagnostics, status management, and repair logging";

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

                <!-- Action Buttons Depending on State -->
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($ticket['status'] === 'assigned'): ?>
                        <form method="POST" action="" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="start_work">
                            <button type="submit" class="btn btn-primary fw-semibold btn-sm shadow-sm px-3">
                                <i class="bi bi-play-circle-fill me-1"></i> Start Work (Mark In Progress)
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if (in_array($ticket['status'], ['in_progress', 'reopened', 'assigned'])): ?>
                        <button type="button" class="btn btn-success fw-semibold btn-sm shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#resolveModal">
                            <i class="bi bi-check2-circle me-1"></i> Mark as Resolved
                        </button>
                    <?php endif; ?>

                    <a href="<?= BASE_URL ?>technician/assigned-tickets.php" class="btn btn-light border btn-sm text-muted">
                        <i class="bi bi-arrow-left me-1"></i> Back to Queue
                    </a>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column: Problem Details, Images, Communication -->
            <div class="col-lg-8">

                <!-- Fault Summary -->
                <div class="cf-card mb-4">
                    <h5 class="cf-card-title mb-3">Reported Problem Description</h5>
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <p class="mb-0 text-dark" style="white-space: pre-line; line-height: 1.6;"><?= htmlspecialchars($ticket['description']) ?></p>
                    </div>

                    <div class="row g-3 small">
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Category</span>
                            <strong class="text-dark"><?= htmlspecialchars($ticket['category_name']) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Reported Severity</span>
                            <?= render_severity_badge($ticket['severity']) ?>
                        </div>
                    </div>
                </div>

                <!-- Attached Photos (Fault & Resolution) -->
                <div class="cf-card mb-4">
                    <h5 class="cf-card-title mb-3">Diagnostic Photo Evidence (<?= count($ticketImages) ?>)</h5>
                    <?php if (empty($ticketImages)): ?>
                        <p class="text-muted small fst-italic mb-0">No photos were attached to this report.</p>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($ticketImages as $img): ?>
                                <div class="col-sm-4">
                                    <div class="border rounded-3 overflow-hidden shadow-sm position-relative">
                                        <a href="<?= BASE_URL . htmlspecialchars($img['file_path']) ?>" target="_blank" data-bs-toggle="tooltip" title="Click to view full image">
                                            <img src="<?= BASE_URL . htmlspecialchars($img['file_path']) ?>" class="w-100" style="height: 140px; object-fit: cover;" alt="Evidence image">
                                        </a>
                                        <div class="p-1 px-2 bg-dark text-white opacity-75 small d-flex justify-content-between" style="font-size: 0.7rem;">
                                            <span class="text-truncate"><?= htmlspecialchars($img['file_name']) ?></span>
                                            <span class="badge <?= ($img['image_type'] === 'resolution') ? 'bg-success' : 'bg-primary' ?>"><?= $img['image_type'] ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Communication Stream & Investigation Notes -->
                <div class="cf-card mb-4">
                    <h5 class="cf-card-title mb-3">Communication & Investigation Notes</h5>

                    <?php if (empty($allComments)): ?>
                        <p class="text-muted small fst-italic mb-3">No messages or internal notes recorded yet.</p>
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

                    <!-- Add Note Form -->
                    <form method="POST" action="" class="p-3 bg-light rounded-3 border">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_note">
                        <label class="form-label small fw-semibold text-secondary mb-1">Add Note / Message</label>
                        <textarea class="form-control mb-2" name="note_text" rows="2" placeholder="Record diagnostic observations or reply to student..." required></textarea>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_internal" id="is_internal" value="1" checked>
                                <label class="form-check-label small text-secondary" for="is_internal">
                                    <i class="bi bi-lock-fill text-warning"></i> Internal staff note (Hidden from student)
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm fw-semibold px-3">
                                <i class="bi bi-save me-1"></i> Save Note
                            </button>
                        </div>
                    </form>
                </div>

            </div>

            <!-- Right Column: Location, Reporter Info & Audit -->
            <div class="col-lg-4">

                <?php if (!empty($ticket['payment_status']) && $ticket['payment_status'] !== 'not_required'): ?>
                    <!-- Material Billing & Surcharge Status -->
                    <div class="cf-card mb-4 border-start border-4 <?= ($ticket['payment_status'] === 'paid') ? 'border-success' : 'border-warning' ?>">
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-2">
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="bi bi-wallet2 text-primary me-1"></i> Part / Material Surcharge
                            </h6>
                            <span class="badge <?= ($ticket['payment_status'] === 'paid') ? 'bg-success' : 'bg-warning text-dark' ?> small text-uppercase">
                                <?= ucfirst($ticket['payment_status']) ?>
                            </span>
                        </div>
                        <div class="small">
                            <div class="fw-bold text-dark fs-6"><?= $currency ?> <?= number_format($ticket['billing_amount'], 2) ?></div>
                            <div class="text-secondary"><?= htmlspecialchars($ticket['billing_description'] ?: 'Material Replacement') ?></div>
                            <?php if ($ticket['payment_status'] === 'paid' && $payment): ?>
                                <div class="text-success small mt-2">
                                    <i class="bi bi-check-circle-fill me-1"></i> Settled via <?= htmlspecialchars($payment['payment_channel'] ?: 'MoMo') ?>
                                </div>
                            <?php else: ?>
                                <div class="text-muted small mt-2">
                                    <i class="bi bi-hourglass-split me-1"></i> Awaiting student settlement via Paystack Sandbox
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Location Pinpoint -->
                <div class="cf-card mb-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-geo-alt-fill text-danger me-1"></i> Site Location
                    </h6>
                    <div class="mb-2">
                        <span class="text-muted small d-block">Building</span>
                        <strong class="text-dark"><?= htmlspecialchars($ticket['building_name']) ?></strong>
                        <span class="badge bg-secondary-subtle text-secondary ms-1 font-monospace"><?= htmlspecialchars($ticket['building_code']) ?></span>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted small d-block">Floor & Room</span>
                        <span class="text-dark"><?= htmlspecialchars($ticket['floor']) ?> — <strong><?= htmlspecialchars($ticket['room_number'] ?? 'General Area') ?></strong></span>
                    </div>
                    <?php if (!empty($ticket['location_details'])): ?>
                        <div class="p-2 bg-light rounded border small text-dark mt-2">
                            <i class="bi bi-pin-map text-primary me-1"></i> <?= htmlspecialchars($ticket['location_details']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Reporter Contact Info -->
                <div class="cf-card mb-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-person-circle text-primary me-1"></i> Student / Reporter
                    </h6>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 44px; height: 44px;">
                            <?= strtoupper(substr($ticket['reporter_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($ticket['reporter_name']) ?></h6>
                            <small class="text-muted d-block"><?= htmlspecialchars($ticket['reporter_dept'] ?? 'Student') ?></small>
                        </div>
                    </div>
                    <div class="small text-secondary">
                        <div class="mb-1"><i class="bi bi-envelope me-1"></i> <a href="mailto:<?= htmlspecialchars($ticket['reporter_email']) ?>" class="text-decoration-none"><?= htmlspecialchars($ticket['reporter_email']) ?></a></div>
                        <div><i class="bi bi-telephone me-1"></i> <a href="tel:<?= htmlspecialchars($ticket['reporter_phone']) ?>" class="text-decoration-none font-monospace fw-bold"><?= htmlspecialchars($ticket['reporter_phone'] ?? 'No phone provided') ?></a></div>
                    </div>
                </div>

                <!-- Admin Instructions -->
                <?php if (!empty($assignmentInfo['instructions'])): ?>
                    <div class="cf-card mb-4 bg-primary-subtle border-primary-subtle">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-info-circle-fill"></i> Dispatch Instructions from Admin</h6>
                        <p class="small text-dark mb-0"><?= htmlspecialchars($assignmentInfo['instructions']) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Audit History -->
                <div class="cf-card mb-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-clock-history text-secondary me-1"></i> Status History
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

<!-- Modal: Mark Resolved -->
<div class="modal fade" id="resolveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_resolved">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-check2-circle"></i> Document Repair & Mark Resolved</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="resolution_notes" class="form-label small fw-bold text-dark">
                            Repair Actions Taken <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" name="resolution_notes" id="resolution_notes" rows="4" 
                                  placeholder="Describe technical diagnostics, repairs performed, and tests completed to verify the fix..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="parts_replaced" class="form-label small fw-semibold text-secondary">
                            Replacement Parts / Materials Used <small class="text-muted">(Optional)</small>
                        </label>
                        <input type="text" class="form-control" name="parts_replaced" id="parts_replaced" placeholder="e.g. Cisco AP firmware flashed, Cat6 RJ45 connector re-crimped, 13A socket plate replaced">
                    </div>

                    <div class="mb-3">
                        <label for="resolution_photos" class="form-label small fw-semibold text-secondary">
                            Upload Proof of Fix Photo <small class="text-muted">(Optional, max 2 images)</small>
                        </label>
                        <input type="file" class="form-control" name="resolution_photos[]" id="resolution_photos" multiple accept=".jpg,.jpeg,.png,.webp">
                        <small class="text-muted" style="font-size: 0.75rem;">Shows completed repair state for administrative quality audit.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm fw-semibold px-4">
                        <i class="bi bi-check-lg me-1"></i> Submit Resolution
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once INCLUDES_PATH . 'footer.php'; ?>

