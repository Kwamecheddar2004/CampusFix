<?php
/**
 * CampusFix - Student Fault Reporting Wizard
 * Multi-photo upload, category selection, campus location tagging & automatic ticket generation.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/UploadService.php';

// Enforce Student Role
require_role('student');

$user = current_user();
$pageTitle = 'Report Campus Fault';
$pageHeading = 'Submit Fault Report';
$pageSubtitle = 'Report IT malfunctions or physical facility maintenance issues to campus teams';

$error = '';
$title = '';
$categoryId = '';
$locationId = '';
$locationDetails = '';
$description = '';
$severity = 'medium';

// Fetch Categories & Campus Buildings for Dropdowns
$categories = [];
$buildings = [];
$locations = [];

try {
    $pdo = get_db_connection();
    $categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
    $locations  = $pdo->query("SELECT * FROM campus_locations WHERE is_active = 1 ORDER BY building_name ASC, floor ASC")->fetchAll();
    
    // Group locations by building code
    $buildings = $pdo->query("SELECT DISTINCT building_code, building_name FROM campus_locations WHERE is_active = 1 ORDER BY building_name ASC")->fetchAll();
} catch (Exception $e) {
    $error = 'Database initialization error: ' . $e->getMessage();
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();

    $title           = trim($_POST['title'] ?? '');
    $categoryId      = (int) ($_POST['category_id'] ?? 0);
    $locationId      = (int) ($_POST['location_id'] ?? 0);
    $locationDetails = trim($_POST['location_details'] ?? '');
    $description     = trim($_POST['description'] ?? '');
    $severity        = strtolower(trim($_POST['severity'] ?? 'medium'));

    // Validation
    $allowedSeverities = ['low', 'medium', 'high', 'critical'];
    if (!in_array($severity, $allowedSeverities, true)) {
        $severity = 'medium';
    }

    if (empty($title) || empty($categoryId) || empty($locationId) || empty($description)) {
        $error = 'Please fill in all required fields (Category, Location, Problem Title, and Description).';
    } elseif (strlen($title) < 5) {
        $error = 'Please provide a descriptive problem title (at least 5 characters).';
    } elseif (strlen($description) < 15) {
        $error = 'Please provide sufficient details in the description (at least 15 characters).';
    } else {
        try {
            // 1. Process Uploaded Photos (Up to 3 images)
            $uploadedPhotos = [];
            if (!empty($_FILES['photos']['name'][0])) {
                $uploadedPhotos = UploadService::handleUploads($_FILES['photos'], TICKET_UPLOADS_PATH, 'uploads/tickets/', 3);
            }

            // 2. Start Transaction
            $pdo->beginTransaction();

            // Generate Ticket Number (e.g. CF-2026-0004)
            $ticketNumber = generate_ticket_number($pdo);

            // Default administrative priority aligned with initial severity
            $priority = ($severity === 'critical') ? 'urgent' : (($severity === 'high') ? 'high' : 'medium');

            $insertStmt = $pdo->prepare("
                INSERT INTO tickets (
                    ticket_number, reporter_id, category_id, location_id, location_details,
                    title, description, severity, priority, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
            ");
            $insertStmt->execute([
                $ticketNumber,
                $user['id'],
                $categoryId,
                $locationId,
                $locationDetails,
                $title,
                $description,
                $severity,
                $priority
            ]);
            $ticketId = (int) $pdo->lastInsertId();

            // 3. Persist Uploaded Photos in ticket_images
            if (!empty($uploadedPhotos)) {
                UploadService::saveTicketImages($pdo, $ticketId, $user['id'], $uploadedPhotos, 'fault');
            }

            // 4. Record Initial Status History
            $historyStmt = $pdo->prepare("
                INSERT INTO ticket_status_history (ticket_id, old_status, new_status, changed_by, notes, created_at)
                VALUES (?, NULL, 'pending', ?, 'Fault report submitted by student.', NOW())
            ");
            $historyStmt->execute([$ticketId, $user['id']]);

            // 5. Create in-app Notification for System Administrators
            $adminIds = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, ticket_id, title, message, channel, status, created_at)
                VALUES (?, ?, ?, ?, 'in_app', 'sent', NOW())
            ");
            foreach ($adminIds as $adminId) {
                $notifStmt->execute([
                    $adminId,
                    $ticketId,
                    "New Fault Reported ({$ticketNumber})",
                    "Student {$user['full_name']} reported a {$severity} severity issue: '{$title}'"
                ]);
            }

            // 6. Security Audit Log
            log_activity($user['id'], 'ticket_created', 'ticket', $ticketId, [
                'ticket_number' => $ticketNumber,
                'category_id'   => $categoryId,
                'severity'      => $severity,
                'photo_count'   => count($uploadedPhotos)
            ]);

            // Commit Transaction
            $pdo->commit();

            flash_message('success', "Ticket {$ticketNumber} submitted successfully! Your problem has been queued for technician assignment.");
            header("Location: " . BASE_URL . "student/ticket-details.php?id=" . $ticketId);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Error saving fault report: ' . $e->getMessage();
        }
    }
}

include_once INCLUDES_PATH . 'header.php';
include_once INCLUDES_PATH . 'sidebar.php';
?>

<div class="d-flex flex-column flex-1 w-100">
    <?php include_once INCLUDES_PATH . 'navbar.php'; ?>

    <div class="container-fluid px-0">
        <div class="row justify-content-center">
            <div class="col-xl-9 col-lg-11">

                <div class="cf-card">
                    <div class="cf-card-header">
                        <div>
                            <h4 class="cf-card-title mb-1">Campus Fault Reporting Form</h4>
                            <small class="text-muted">Provide accurate details to assist technicians with swift diagnosis and repair</small>
                        </div>
                        <a href="<?= BASE_URL ?>student/dashboard.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-x-lg"></i> Cancel
                        </a>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                            <div><?= htmlspecialchars($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <?= csrf_field() ?>

                        <!-- Section 1: Classification & Location -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                                <i class="bi bi-1-circle text-primary me-1"></i> Issue Classification & Location
                            </h6>

                            <div class="row g-3">
                                <!-- Category -->
                                <div class="col-md-6">
                                    <label for="category_id" class="form-label small fw-semibold text-secondary">
                                        Fault Category <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category...</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>" <?= ($categoryId == $cat['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['name']) ?> (<?= htmlspecialchars($cat['department_in_charge']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please choose a fault category.</div>
                                </div>

                                <!-- Campus Location -->
                                <div class="col-md-6">
                                    <label for="location_id" class="form-label small fw-semibold text-secondary">
                                        Campus Location / Room <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="location_id" name="location_id" required>
                                        <option value="">Select Specific Location...</option>
                                        <?php foreach ($locations as $loc): ?>
                                            <option value="<?= $loc['id'] ?>" <?= ($locationId == $loc['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($loc['building_name']) ?> — <?= htmlspecialchars($loc['floor']) ?> (<?= htmlspecialchars($loc['room_number'] ?? 'General Area') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select where the fault is located.</div>
                                </div>

                                <!-- Specific Location Pinpoint Notes -->
                                <div class="col-12">
                                    <label for="location_details" class="form-label small fw-semibold text-secondary">
                                        Pinpoint Area Details <small class="text-muted">(Optional)</small>
                                    </label>
                                    <input type="text" class="form-control" id="location_details" name="location_details" 
                                           value="<?= htmlspecialchars($locationDetails) ?>" 
                                           placeholder="e.g. Workstation #14, 3rd window on the left, near the lecturer's podium">
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Problem Description -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                                <i class="bi bi-2-circle text-primary me-1"></i> Problem Title & Description
                            </h6>

                            <div class="mb-3">
                                <label for="title" class="form-label small fw-semibold text-secondary">
                                    Incident Summary / Title <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?= htmlspecialchars($title) ?>" 
                                       placeholder="e.g. Cisco Wi-Fi router keeps dropping internet connection in Lab 1" required minlength="5">
                                <div class="invalid-feedback">Please write a clear, concise title (at least 5 characters).</div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label small fw-semibold text-secondary">
                                    Detailed Problem Description <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control" id="description" name="description" rows="4" 
                                          placeholder="Describe what is broken, error messages, physical symptoms, and how long this has occurred..." required minlength="15"><?= htmlspecialchars($description) ?></textarea>
                                <div class="invalid-feedback">Please provide detailed information (at least 15 characters).</div>
                            </div>
                        </div>

                        <!-- Section 3: Severity / Impact Level -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                                <i class="bi bi-3-circle text-primary me-1"></i> Severity & Operational Impact
                            </h6>

                            <div class="row g-2">
                                <div class="col-sm-6 col-md-3">
                                    <label class="p-3 border rounded-3 w-100 d-block cursor-pointer position-relative bg-light">
                                        <input type="radio" name="severity" value="low" class="form-check-input me-2" <?= ($severity === 'low') ? 'checked' : '' ?>>
                                        <span class="fw-bold text-secondary">Low</span>
                                        <p class="text-muted small mb-0 mt-1" style="font-size: 0.75rem;">Minor cosmetic issue or non-disruptive fault.</p>
                                    </label>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <label class="p-3 border rounded-3 w-100 d-block cursor-pointer position-relative bg-light">
                                        <input type="radio" name="severity" value="medium" class="form-check-input me-2" <?= ($severity === 'medium') ? 'checked' : '' ?>>
                                        <span class="fw-bold text-primary">Medium</span>
                                        <p class="text-muted small mb-0 mt-1" style="font-size: 0.75rem;">Single device broken, workarounds exist.</p>
                                    </label>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <label class="p-3 border rounded-3 w-100 d-block cursor-pointer position-relative bg-light">
                                        <input type="radio" name="severity" value="high" class="form-check-input me-2" <?= ($severity === 'high') ? 'checked' : '' ?>>
                                        <span class="fw-bold text-warning-emphasis">High</span>
                                        <p class="text-muted small mb-0 mt-1" style="font-size: 0.75rem;">Entire lecture hall projector or lab network down.</p>
                                    </label>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <label class="p-3 border rounded-3 w-100 d-block cursor-pointer position-relative bg-light border-danger">
                                        <input type="radio" name="severity" value="critical" class="form-check-input me-2" <?= ($severity === 'critical') ? 'checked' : '' ?>>
                                        <span class="fw-bold text-danger">Critical Hazard</span>
                                        <p class="text-muted small mb-0 mt-1" style="font-size: 0.75rem;">Sparks, flooding pipe, electrical shock risk.</p>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Section 4: Photographic Proof -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                                <i class="bi bi-4-circle text-primary me-1"></i> Photo Attachments <small class="text-muted fw-normal">(Optional, max 3 photos)</small>
                            </h6>

                            <div class="p-3 border border-dashed rounded-3 bg-light text-center">
                                <i class="bi bi-cloud-arrow-up text-primary fs-2 mb-2 d-block"></i>
                                <label for="ticket_photos" class="btn btn-outline-primary btn-sm fw-semibold mb-2">
                                    <i class="bi bi-images me-1"></i> Choose Photos
                                </label>
                                <input type="file" class="d-none" id="ticket_photos" name="photos[]" multiple accept=".jpg,.jpeg,.png,.webp">
                                <div class="small text-muted">
                                    Accepted formats: <strong>JPG, JPEG, PNG, WEBP</strong> (Max 5MB each, up to 3 images)
                                </div>

                                <!-- Dynamic Preview Container -->
                                <div id="photo_preview_container" class="row g-2 justify-content-center mt-3"></div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                            <button type="reset" class="btn btn-light border text-muted">Reset Form</button>
                            <button type="submit" class="btn btn-primary btn-lg fw-semibold px-4 shadow-sm">
                                <i class="bi bi-send-check me-1"></i> Submit Fault Report
                            </button>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include_once INCLUDES_PATH . 'footer.php'; ?>

