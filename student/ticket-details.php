<?php
/**
 * CampusFix - Comprehensive Ticket Tracking & Investigation Detail View
 * Features interactive status stepper, photo gallery modal, timeline audit, comments & closure/reopen actions.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/PaymentService.php';

require_login();

$user = current_user();
$ticketId = (int) ($_GET['id'] ?? 0);

if ($ticketId <= 0) {
    flash_message('danger', 'Invalid ticket identifier.');
    header("Location: " . BASE_URL . "{$user['role']}/dashboard.php");
    exit;
}

$pdo = get_db_connection();
$payment = PaymentService::getPaymentByTicket($ticketId);
$currency = PaymentService::getCurrencySymbol();
$paystackPageUrl = PaymentService::getPaymentPageUrl();

// 1. Fetch Ticket Record with Relations
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
    header("Location: " . BASE_URL . "{$user['role']}/dashboard.php");
    exit;
}

// Security Check: Students can only view their own tickets (Technicians & Admins can view according to role)
if ($user['role'] === 'student' && (int) $ticket['reporter_id'] !== (int) $user['id']) {
    http_response_code(403);
    include_once INCLUDES_PATH . '403.php';
    exit;
}

// 2. Handle Student Actions: Confirm Resolution (Close) OR Reopen Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    enforce_csrf();
    $action = $_POST['action'];

    // Action A: Confirm Resolution & Rate Service
    if ($action === 'confirm_resolution' && $ticket['status'] === 'resolved') {
        $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
        $feedback = trim($_POST['feedback'] ?? '');

        $closeStmt = $pdo->prepare("
            UPDATE tickets 
            SET status = 'closed', satisfaction_rating = ?, satisfaction_feedback = ?, closed_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $closeStmt->execute([$rating, $feedback, $ticketId]);

        // Status History
        $histStmt = $pdo->prepare("
            INSERT INTO ticket_status_history (ticket_id, old_status, new_status, changed_by, notes, created_at)
            VALUES (?, 'resolved', 'closed', ?, ?, NOW())
        ");
        $histStmt->execute([$ticketId, $user['id'], "Student confirmed repair resolution with a {$rating}-star rating."]);

        log_activity($user['id'], 'ticket_closed', 'ticket', $ticketId, ['rating' => $rating]);
        flash_message('success', 'Thank you! You have confirmed the repair and closed this ticket.');
        header("Location: " . BASE_URL . "student/ticket-details.php?id=" . $ticketId);
        exit;
    }

    // Action B: Reopen Ticket
    if ($action === 'reopen_ticket' && $ticket['status'] === 'resolved') {
        $reopenReason = trim($_POST['reopen_reason'] ?? '');
        if (empty($reopenReason)) {
            flash_message('danger', 'Please provide a clear reason explaining why the issue is not fixed.');
        } else {
            $reopenStmt = $pdo->prepare("
                UPDATE tickets 
                SET status = 'reopened', updated_at = NOW()
                WHERE id = ?
            ");
            $reopenStmt->execute([$ticketId]);

            // Status History
            $histStmt = $pdo->prepare("
                INSERT INTO ticket_status_history (ticket_id, old_status, new_status, changed_by, notes, created_at)
                VALUES (?, 'resolved', 'reopened', ?, ?, NOW())
            ");
            $histStmt->execute([$ticketId, $user['id'], "Reopened by student: {$reopenReason}"]);

            // Notify Assigned Tech and Admin
            if (!empty($ticket['assigned_to'])) {
                $notifStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, ticket_id, title, message, channel, status, created_at)
                    VALUES (?, ?, ?, ?, 'in_app', 'sent', NOW())
                ");
                $notifStmt->execute([
                    $ticket['assigned_to'],
                    $ticketId,
                    "Ticket Reopened ({$ticket['ticket_number']})",
                    "Student {$user['full_name']} stated the fault persists: {$reopenReason}"
                ]);
            }

            log_activity($user['id'], 'ticket_reopened', 'ticket', $ticketId, ['reason' => $reopenReason]);
            flash_message('warning', 'Ticket has been reopened and returned to the technical dispatch queue.');
            header("Location: " . BASE_URL . "student/ticket-details.php?id=" . $ticketId);
            exit;
        }
    }

    // Action C: Post Public Clarification Comment
    if ($action === 'add_comment') {
        $commentText = trim($_POST['comment'] ?? '');
        if (!empty($commentText)) {
            $commentStmt = $pdo->prepare("
                INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at)
                VALUES (?, ?, ?, 0, NOW())
            ");
            $commentStmt->execute([$ticketId, $user['id'], $commentText]);

            log_activity($user['id'], 'comment_added', 'ticket', $ticketId);
            flash_message('success', 'Your comment has been posted.');
            header("Location: " . BASE_URL . "student/ticket-details.php?id=" . $ticketId);
            exit;
        }
    }
}

// 3. Fetch Photos
$imgStmt = $pdo->prepare("SELECT * FROM ticket_images WHERE ticket_id = ? ORDER BY id ASC");
$imgStmt->execute([$ticketId]);
$photos = $imgStmt->fetchAll();

// 4. Fetch Status Audit History
$histStmt = $pdo->prepare("
    SELECT h.*, u.full_name as author_name, u.role as author_role
    FROM ticket_status_history h
    JOIN users u ON h.changed_by = u.id
    WHERE h.ticket_id = ?
    ORDER BY h.created_at ASC
");
$histStmt->execute([$ticketId]);
$statusHistory = $histStmt->fetchAll();

// 5. Fetch Public Comments
$comStmt = $pdo->prepare("
    SELECT c.*, u.full_name, u.role, u.department
    FROM ticket_comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.ticket_id = ? AND c.is_internal = 0
    ORDER BY c.created_at ASC
");
$comStmt->execute([$ticketId]);
$comments = $comStmt->fetchAll();

$pageTitle = "Ticket {$ticket['ticket_number']}";
$pageHeading = "Ticket Tracking: {$ticket['ticket_number']}";
$pageSubtitle = "Real-time lifecycle monitoring and repair updates";

include_once INCLUDES_PATH . 'header.php';
include_once INCLUDES_PATH . 'sidebar.php';

// Pipeline Stepper Progress Calculation
$steps = ['pending' => 1, 'assigned' => 2, 'in_progress' => 3, 'resolved' => 4, 'closed' => 5];
$currentStep = $steps[$ticket['status']] ?? 1;
?>

<div class="d-flex flex-column flex-1 w-100">
    <?php include_once INCLUDES_PATH . 'navbar.php'; ?>

    <div class="container-fluid px-0">

        <!-- Top Header Card -->
        <div class="cf-card mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom mb-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="p-3 rounded-3 bg-light border text-primary fs-3">
                        <i class="bi <?= htmlspecialchars($ticket['category_icon']) ?>"></i>
                    </span>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h4 class="fw-bold text-dark font-monospace mb-0"><?= htmlspecialchars($ticket['ticket_number']) ?></h4>
                            <?= render_status_badge($ticket['status']) ?>
                        </div>
                        <h6 class="fw-semibold text-secondary mt-1 mb-0"><?= htmlspecialchars($ticket['title']) ?></h6>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <?php if ($ticket['status'] === 'resolved'): ?>
                        <button type="button" class="btn btn-success fw-semibold btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#confirmModal">
                            <i class="bi bi-check2-circle me-1"></i> Confirm Fix & Close
                        </button>
                        <button type="button" class="btn btn-outline-danger fw-semibold btn-sm" data-bs-toggle="modal" data-bs-target="#reopenModal">
                            <i class="bi bi-arrow-clockwise me-1"></i> Reopen Ticket
                        </button>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>student/my-tickets.php" class="btn btn-light border btn-sm text-muted">
                        <i class="bi bi-arrow-left me-1"></i> Back to List
                    </a>
                </div>
            </div>

            <!-- Visual Pipeline Stepper -->
            <div class="py-2">
                <div class="d-flex justify-content-between position-relative px-2">
                    <div class="position-absolute top-50 start-0 translate-middle-y w-100 bg-secondary-subtle" style="height: 4px; z-index: 1;"></div>
                    <?php 
                    $stageNames = [
                        1 => ['label' => 'Pending', 'icon' => 'bi-clock'],
                        2 => ['label' => 'Assigned', 'icon' => 'bi-person-check'],
                        3 => ['label' => 'In Progress', 'icon' => 'bi-tools'],
                        4 => ['label' => 'Resolved', 'icon' => 'bi-check2'],
                        5 => ['label' => 'Closed', 'icon' => 'bi-lock']
                    ];
                    foreach ($stageNames as $stepNum => $st):
                        $isPassed = ($currentStep >= $stepNum);
                        $isActive = ($currentStep === $stepNum);
                    ?>
                        <div class="text-center position-relative" style="z-index: 2;">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1 fw-bold shadow-sm <?= $isPassed ? 'bg-primary text-white' : 'bg-white border text-muted' ?>" style="width: 38px; height: 38px; font-size: 0.9rem;">
                                <i class="bi <?= $st['icon'] ?>"></i>
                            </div>
                            <span class="small fw-semibold <?= $isActive ? 'text-primary' : ($isPassed ? 'text-dark' : 'text-muted') ?>">
                                <?= $st['label'] ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($ticket['payment_status']) && $ticket['payment_status'] !== 'not_required'): ?>
            <!-- Maintenance Billing & Payment Surcharge Banner -->
            <div class="cf-card mb-4 border-start border-4 <?= ($ticket['payment_status'] === 'paid') ? 'border-success' : (($ticket['payment_status'] === 'pending') ? 'border-warning' : 'border-secondary') ?> shadow-sm">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 rounded-circle <?= ($ticket['payment_status'] === 'paid') ? 'bg-success-subtle text-success' : (($ticket['payment_status'] === 'pending') ? 'bg-warning-subtle text-warning' : 'bg-light text-muted') ?> fs-3">
                            <i class="bi <?= ($ticket['payment_status'] === 'paid') ? 'bi-patch-check-fill' : (($ticket['payment_status'] === 'pending') ? 'bi-credit-card-2-front-fill' : 'bi-slash-circle') ?>"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                <h6 class="fw-bold mb-0 text-dark">
                                    <?= ($ticket['payment_status'] === 'paid') ? 'Maintenance Surcharge Settled' : (($ticket['payment_status'] === 'pending') ? 'Action Required: Maintenance Surcharge Payment' : 'Maintenance Surcharge Waived') ?>
                                </h6>
                                <span class="badge <?= ($ticket['payment_status'] === 'paid') ? 'bg-success' : (($ticket['payment_status'] === 'pending') ? 'bg-warning text-dark' : 'bg-secondary') ?> text-uppercase small">
                                    <?= ucfirst($ticket['payment_status']) ?>
                                </span>
                                <span class="badge bg-primary-subtle text-primary border border-primary small">
                                    <i class="bi bi-shield-check me-1"></i> Paystack
                                </span>
                            </div>
                            <div class="text-secondary small">
                                <strong>Amount:</strong> <span class="fw-bold text-dark fs-6"><?= $currency ?> <?= number_format($ticket['billing_amount'], 2) ?></span>
                                <?php if (!empty($ticket['billing_description'])): ?>
                                    &bull; <span><?= htmlspecialchars($ticket['billing_description']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($ticket['payment_status'] === 'paid' && $payment): ?>
                                <div class="text-muted small mt-1">
                                    Settled via <strong><?= htmlspecialchars($payment['payment_channel'] ?: 'Mobile Money') ?></strong> on <?= date('M j, Y g:i A', strtotime($payment['paid_at'] ?: $payment['created_at'])) ?> &bull; Receipt: <code><?= htmlspecialchars($payment['receipt_number']) ?></code>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <?php if ($ticket['payment_status'] === 'pending'): ?>
                            <button type="button" class="btn btn-warning fw-bold px-3 py-2 shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#paymentModal">
                                <i class="bi bi-wallet2"></i> Pay <?= $currency ?> <?= number_format($ticket['billing_amount'], 2) ?> Now
                            </button>
                        <?php elseif ($ticket['payment_status'] === 'paid' && $payment): ?>
                            <a href="<?= BASE_URL ?>student/receipt.php?ref=<?= urlencode($payment['receipt_number']) ?>" target="_blank" class="btn btn-outline-success btn-sm fw-semibold shadow-sm">
                                <i class="bi bi-printer me-1"></i> View Official Receipt
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left Column: Details, Photos & Comments -->
            <div class="col-lg-8">

                <!-- Incident Description -->
                <div class="cf-card mb-4">
                    <h5 class="cf-card-title mb-3">Fault Details & Description</h5>
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <p class="mb-0 text-dark" style="white-space: pre-line; line-height: 1.6;"><?= htmlspecialchars($ticket['description']) ?></p>
                    </div>

                    <div class="row g-3 small">
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Fault Category</span>
                            <strong class="text-dark"><?= htmlspecialchars($ticket['category_name']) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Department in Charge</span>
                            <strong class="text-dark"><?= htmlspecialchars($ticket['department_in_charge']) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Severity Level</span>
                            <?= render_severity_badge($ticket['severity']) ?>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Administrative Priority</span>
                            <?= render_priority_badge($ticket['priority']) ?>
                        </div>
                    </div>
                </div>

                <!-- Photographic Evidence -->
                <div class="cf-card mb-4">
                    <h5 class="cf-card-title mb-3">Photographic Proof (<?= count($photos) ?> Attachments)</h5>
                    <?php if (empty($photos)): ?>
                        <div class="p-3 bg-light rounded-3 text-muted small text-center">
                            <i class="bi bi-camera-slash fs-4 d-block mb-1 text-secondary"></i>
                            No photos were attached to this report.
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($photos as $img): ?>
                                <div class="col-sm-4">
                                    <div class="border rounded-3 overflow-hidden shadow-sm position-relative">
                                        <a href="<?= BASE_URL . htmlspecialchars($img['file_path']) ?>" target="_blank" data-bs-toggle="tooltip" title="Click to view full image">
                                            <img src="<?= BASE_URL . htmlspecialchars($img['file_path']) ?>" class="w-100" style="height: 140px; object-fit: cover;" alt="Fault image">
                                        </a>
                                        <div class="p-1 px-2 bg-dark text-white opacity-75 small text-truncate" style="font-size: 0.7rem;">
                                            <?= htmlspecialchars($img['file_name']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Discussion & Comments -->
                <div class="cf-card mb-4">
                    <h5 class="cf-card-title mb-3">Communication & Clarifications</h5>

                    <?php if (empty($comments)): ?>
                        <p class="text-muted small fst-italic mb-3">No comments yet. Send a clarification below if needed.</p>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3 mb-4">
                            <?php foreach ($comments as $com): ?>
                                <div class="p-3 rounded-3 border <?= ($com['user_id'] == $user['id']) ? 'bg-primary-subtle border-primary-subtle ms-4' : 'bg-light me-4' ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold small text-dark">
                                            <?= htmlspecialchars($com['full_name']) ?> 
                                            <span class="badge bg-secondary-subtle text-secondary ms-1" style="font-size: 0.65rem; text-transform: uppercase;">
                                                <?= htmlspecialchars($com['role']) ?>
                                            </span>
                                        </span>
                                        <small class="text-muted" style="font-size: 0.75rem;"><?= time_ago($com['created_at']) ?></small>
                                    </div>
                                    <p class="mb-0 small text-dark" style="white-space: pre-line;"><?= htmlspecialchars($com['comment']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Post New Comment Form -->
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_comment">
                        <div class="input-group">
                            <input type="text" name="comment" class="form-control" placeholder="Write a message or reply to technician..." required>
                            <button type="submit" class="btn btn-primary fw-semibold px-3">
                                <i class="bi bi-send"></i> Send
                            </button>
                        </div>
                    </form>
                </div>

            </div>

            <!-- Right Column: Location, Technician & Audit Timeline -->
            <div class="col-lg-4">

                <!-- Location Pinpoint Card -->
                <div class="cf-card mb-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-geo-alt-fill text-danger me-1"></i> Incident Location
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
                            <i class="bi bi-info-circle text-primary me-1"></i> <?= htmlspecialchars($ticket['location_details']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Assigned Technician Card -->
                <div class="cf-card mb-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-person-badge-fill text-primary me-1"></i> Assigned Field Technician
                    </h6>
                    <?php if (!empty($ticket['technician_name'])): ?>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 44px; height: 44px;">
                                <?= strtoupper(substr($ticket['technician_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($ticket['technician_name']) ?></h6>
                                <small class="text-muted d-block"><?= htmlspecialchars($ticket['technician_email']) ?></small>
                                <small class="text-primary fw-medium"><i class="bi bi-telephone"></i> <?= htmlspecialchars($ticket['technician_phone'] ?? 'Campus Extension') ?></small>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="p-3 bg-light rounded text-center small text-muted">
                            <i class="bi bi-hourglass-split fs-4 d-block mb-1 text-warning"></i>
                            Awaiting assignment by administrative helpdesk.
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($ticket['resolution_notes'])): ?>
                        <div class="mt-3 p-3 bg-success-subtle rounded-3 border border-success-subtle">
                            <h6 class="fw-bold text-success small mb-1"><i class="bi bi-check-circle-fill"></i> Technician Resolution Summary</h6>
                            <p class="small text-dark mb-0"><?= htmlspecialchars($ticket['resolution_notes']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Status Transition History / Audit Trail -->
                <div class="cf-card mb-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-clock-history text-secondary me-1"></i> Status Audit History
                    </h6>
                    <div class="timeline ps-3 border-start border-2 border-primary-subtle position-relative ms-2">
                        <?php foreach ($statusHistory as $h): ?>
                            <div class="position-relative mb-3 ps-3">
                                <span class="position-absolute start-0 top-0 translate-middle p-1 bg-primary border border-light rounded-circle" style="left: -1px !important;"></span>
                                <div class="fw-semibold text-dark small" style="text-transform: capitalize;">
                                    Status: <code><?= htmlspecialchars($h['new_status']) ?></code>
                                </div>
                                <div class="text-muted" style="font-size: 0.725rem;">
                                    by <?= htmlspecialchars($h['author_name']) ?> (<?= htmlspecialchars($h['author_role']) ?>) • <?= time_ago($h['created_at']) ?>
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

<!-- Modal A: Confirm Resolution & Rate Service -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="confirm_resolution">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-patch-check"></i> Confirm Repair Resolution</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-dark small">
                        Please confirm that technician <strong><?= htmlspecialchars($ticket['technician_name'] ?? 'Technician') ?></strong> has fully fixed this problem. Your rating helps the university improve campus maintenance standards.
                    </p>

                    <div class="mb-3 text-center py-2 bg-light rounded-3">
                        <label class="form-label small fw-bold text-dark d-block mb-2">Service Quality Rating</label>
                        <div class="d-flex justify-content-center gap-3">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <div class="form-check form-check-inline m-0">
                                    <input class="form-check-input" type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" <?= ($i === 5) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold small text-warning" for="star<?= $i ?>"><?= $i ?> ★</label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="feedback" class="form-label small fw-semibold text-secondary">Comments / Feedback (Optional)</label>
                        <textarea class="form-control" name="feedback" id="feedback" rows="3" placeholder="e.g. Great job, Wi-Fi is fast and steady now!"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm fw-semibold px-3">Confirm & Close Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal B: Reopen Ticket -->
<div class="modal fade" id="reopenModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reopen_ticket">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-arrow-clockwise"></i> Reopen Maintenance Ticket</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-dark small">
                        If the fault was not properly resolved or has recurred, please explain what symptoms persist. This ticket will be returned to the technical dispatch queue.
                    </p>
                    <div class="mb-3">
                        <label for="reopen_reason" class="form-label small fw-semibold text-secondary">Reason for Reopening <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="reopen_reason" id="reopen_reason" rows="3" placeholder="e.g. The projector lamp is still flickering and turns off after 5 minutes..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-semibold px-3">Confirm Reopen Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal C: Paystack Payment Gateway -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary px-2 py-1 small">PAYSTACK</span>
                    <h5 class="modal-title fw-bold fs-6 mb-0">Pay Maintenance Surcharge via Paystack</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4" id="paymentModalContent">
                <!-- Surcharge Summary Box -->
                <div class="p-3 bg-light rounded-3 border mb-4 text-center">
                    <small class="text-muted text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Amount Payable</small>
                    <div class="fs-2 fw-bold text-dark my-1">
                        <?= $currency ?> <?= number_format($ticket['billing_amount'], 2) ?>
                    </div>
                    <div class="badge bg-warning-subtle text-warning border border-warning small">
                        <i class="bi bi-tag-fill me-1"></i> <?= htmlspecialchars($ticket['billing_description'] ?: 'Repair Material Surcharge') ?>
                    </div>
                    <div class="mt-2 text-muted small">
                        Ticket ID: <strong><?= htmlspecialchars($ticket['ticket_number']) ?></strong> &bull; Payer: <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                    </div>
                </div>

                <div id="paymentActionsContainer">
                    <!-- Step 1: Open Official Paystack Link -->
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-primary rounded-pill px-2 py-1">Step 1</span>
                            <strong class="text-dark small">Complete Payment on Paystack</strong>
                        </div>
                        <p class="text-secondary small mb-3">
                            Click below to open your secure Paystack payment page in a new window. You can pay with <strong>Mobile Money (MTN, Telecel, AT)</strong> or <strong>Card</strong>:
                        </p>
                        <a href="<?= htmlspecialchars($paystackPageUrl) ?>" target="_blank" class="btn btn-primary w-100 fw-bold py-3 shadow-sm d-flex align-items-center justify-content-center gap-2 mb-2">
                            <i class="bi bi-box-arrow-up-right fs-5"></i> Open Paystack Payment Page
                        </a>
                        <small class="text-muted d-block text-center font-monospace" style="font-size: 0.72rem;">
                            <?= htmlspecialchars($paystackPageUrl) ?>
                        </small>
                    </div>

                    <!-- Step 2: Confirm & Issue Official Voucher -->
                    <div class="p-3 bg-white rounded-3 border">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-success rounded-pill px-2 py-1">Step 2</span>
                            <strong class="text-dark small">Issue Official Receipt Voucher</strong>
                        </div>
                        <p class="text-muted small mb-3" style="font-size: 0.8rem;">
                            After completing your transaction on the Paystack page, click below to confirm your payment in CampusFix and generate your printable receipt:
                        </p>
                        <button type="button" class="btn btn-success w-100 fw-bold py-2 shadow-sm d-flex align-items-center justify-content-center gap-2" id="btnConfirmPaystack" onclick="executeSandboxPayment('paystack')">
                            <i class="bi bi-check2-circle fs-5"></i> I Have Paid — Confirm & View Receipt
                        </button>
                    </div>
                </div>

                <!-- Processing Screen Overlay (Hidden initially) -->
                <div id="paymentProcessingScreen" class="d-none text-center py-4">
                    <div class="spinner-border text-primary mb-3" style="width: 3.5rem; height: 3.5rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-1" id="procTitle">Verifying Paystack Surcharge...</h5>
                    <p class="text-secondary small mb-0" id="procSubtitle">Validating Paystack transaction & issuing official voucher...</p>
                </div>

                <!-- Success Screen Overlay (Hidden initially) -->
                <div id="paymentSuccessScreen" class="d-none text-center py-4">
                    <div class="bg-success text-white rounded-circle d-inline-flex p-3 mb-3 shadow">
                        <i class="bi bi-check2-circle fs-1"></i>
                    </div>
                    <h5 class="fw-bold text-success mb-1">Payment Successfully Confirmed!</h5>
                    <p class="text-secondary small mb-3" id="procReceiptText">Official receipt generated. Redirecting to receipt voucher...</p>
                    <a href="#" id="procReceiptBtn" class="btn btn-outline-success btn-sm fw-semibold">
                        <i class="bi bi-receipt me-1"></i> View Receipt Now
                    </a>
                </div>
            </div>

            <div class="modal-footer bg-light py-2">
                <small class="text-muted w-100 text-center" style="font-size: 0.75rem;">
                    <i class="bi bi-shield-lock-fill text-success me-1"></i> Secured exclusively by Paystack &bull; Official CampusFix Gateway
                </small>
            </div>
        </div>
    </div>
</div>

<script>
async function executeSandboxPayment(type) {
    const ticketId = <?= $ticketId ?>;
    const channel = 'Paystack Online';

    // Show Processing State
    document.getElementById('paymentActionsContainer').classList.add('d-none');
    document.getElementById('paymentProcessingScreen').classList.remove('d-none');

    try {
        // Step 1: Initialize transaction via API
        const initResp = await fetch('<?= BASE_URL ?>api/payment.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'init_checkout',
                ticket_id: ticketId
            })
        });

        const initData = await initResp.json();
        if (!initData.success) {
            throw new Error(initData.message || 'Initialization failed');
        }

        const paymentRef = initData.data.reference;

        document.getElementById('procTitle').innerText = 'Verifying Paystack Surcharge...';
        document.getElementById('procSubtitle').innerText = 'Validating reference ' + paymentRef + ' & issuing official voucher...';

        await new Promise(r => setTimeout(r, 1200));

        // Step 2: Verify transaction
        const verifyResp = await fetch('<?= BASE_URL ?>api/payment.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'verify_payment',
                reference: paymentRef,
                channel: channel
            })
        });

        const verifyData = await verifyResp.json();
        if (!verifyData.success) {
            throw new Error(verifyData.message || 'Payment verification failed');
        }

        // Step 3: Show Success State
        document.getElementById('paymentProcessingScreen').classList.add('d-none');
        document.getElementById('paymentSuccessScreen').classList.remove('d-none');
        document.getElementById('procReceiptText').innerText = 'Receipt #' + verifyData.receipt_number + ' has been registered.';
        document.getElementById('procReceiptBtn').href = verifyData.receipt_url;

        // Redirect to receipt after 1.5 seconds
        setTimeout(() => {
            window.location.href = verifyData.receipt_url;
        }, 1500);

    } catch (err) {
        alert('Payment Error: ' + err.message);
        document.getElementById('paymentProcessingScreen').classList.add('d-none');
        document.getElementById('paymentActionsContainer').classList.remove('d-none');
    }
}
</script>

        const verifyData = await verifyResp.json();
        if (!verifyData.success) {
            throw new Error(verifyData.message || 'Payment verification failed');
        }

        // Step 4: Show Success State
        document.getElementById('paymentProcessingScreen').classList.add('d-none');
        document.getElementById('paymentSuccessScreen').classList.remove('d-none');
        document.getElementById('procReceiptText').innerText = 'Receipt #' + verifyData.receipt_number + ' has been registered.';
        document.getElementById('procReceiptBtn').href = verifyData.receipt_url;

        // Redirect to receipt after 1.8 seconds
        setTimeout(() => {
            window.location.href = verifyData.receipt_url;
        }, 1800);

    } catch (err) {
        alert('Payment Error: ' + err.message);
        document.getElementById('paymentProcessingScreen').classList.add('d-none');
        document.getElementById('paymentMethodTabs').classList.remove('d-none');
        document.getElementById('paymentMethodContent').classList.remove('d-none');
    }
}
</script>

<?php include_once INCLUDES_PATH . 'footer.php'; ?>

