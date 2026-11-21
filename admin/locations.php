<?php
/**
 * CampusFix - Campus Buildings & Locations Management
 * Allows administrators to manage standardized physical geography.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$user = current_user();
$pageTitle = 'Campus Locations';
$pageHeading = 'Campus Locations & Buildings Registry';
$pageSubtitle = 'Manage campus infrastructure geography, buildings, floors, and specific rooms';

$pdo = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
    $action = $_POST['action'] ?? '';

    // Action 1: Add Location
    if ($action === 'add_location') {
        $buildingName = trim($_POST['building_name'] ?? '');
        $buildingCode = strtoupper(trim($_POST['building_code'] ?? ''));
        $floor        = trim($_POST['floor'] ?? 'Ground Floor');
        $roomNumber   = trim($_POST['room_number'] ?? '');
        $description  = trim($_POST['area_description'] ?? '');

        if (!empty($buildingName) && !empty($buildingCode)) {
            $insStmt = $pdo->prepare("
                INSERT INTO campus_locations (building_name, building_code, floor, room_number, area_description, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            $insStmt->execute([$buildingName, $buildingCode, $floor, $roomNumber, $description]);

            log_activity($user['id'], 'location_created', 'location', (int)$pdo->lastInsertId(), ['building' => $buildingName]);
            flash_message('success', "Location '{$buildingName} - {$roomNumber}' added successfully.");
            header("Location: " . BASE_URL . "admin/locations.php");
            exit;
        }
    }

    // Action 2: Toggle Active Status
    if ($action === 'toggle_active') {
        $locId = (int) ($_POST['location_id'] ?? 0);
        $currStatus = (int) ($_POST['current_status'] ?? 1);
        $newStatus = ($currStatus === 1) ? 0 : 1;

        $upStmt = $pdo->prepare("UPDATE campus_locations SET is_active = ? WHERE id = ?");
        $upStmt->execute([$newStatus, $locId]);

        flash_message('success', 'Location status updated.');
        header("Location: " . BASE_URL . "admin/locations.php");
        exit;
    }
}

// Fetch locations with incident counts
$locStmt = $pdo->query("
    SELECT l.*,
           COUNT(t.id) as total_incidents,
           SUM(CASE WHEN t.status NOT IN ('resolved', 'closed') THEN 1 ELSE 0 END) as open_incidents
    FROM campus_locations l
    LEFT JOIN tickets t ON l.id = t.location_id
    GROUP BY l.id, l.building_name, l.building_code, l.floor, l.room_number, l.area_description, l.is_active, l.created_at
    ORDER BY l.building_name ASC, l.floor ASC
");
$locations = $locStmt->fetchAll();

include_once INCLUDES_PATH . 'header.php';
include_once INCLUDES_PATH . 'sidebar.php';
?>

<div class="d-flex flex-column flex-1 w-100">
    <?php include_once INCLUDES_PATH . 'navbar.php'; ?>

    <div class="container-fluid px-0">

        <div class="cf-card mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h5 class="cf-card-title mb-1">Campus Physical Facilities</h5>
                    <small class="text-muted">Standardized buildings and rooms registry</small>
                </div>
                <button type="button" class="btn btn-primary btn-sm fw-semibold shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                    <i class="bi bi-geo-alt-fill me-1"></i> Register New Location
                </button>
            </div>
        </div>

        <div class="cf-card">
            <div class="table-responsive">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Building Name</th>
                            <th>Floor Level</th>
                            <th>Room / Facility</th>
                            <th>Description</th>
                            <th>Active Reports</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locations as $loc): ?>
                            <tr>
                                <td><code class="fw-bold"><?= htmlspecialchars($loc['building_code']) ?></code></td>
                                <td><strong class="text-dark small"><?= htmlspecialchars($loc['building_name']) ?></strong></td>
                                <td class="small text-secondary"><?= htmlspecialchars($loc['floor']) ?></td>
                                <td><span class="badge bg-light text-dark border fw-semibold"><?= htmlspecialchars($loc['room_number'] ?? 'General Area') ?></span></td>
                                <td class="small text-muted" style="max-width: 250px;"><?= htmlspecialchars($loc['area_description'] ?? '—') ?></td>
                                <td>
                                    <?php if ($loc['open_incidents'] > 0): ?>
                                        <span class="badge bg-danger rounded-pill px-2 py-1"><?= $loc['open_incidents'] ?> Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success">Clear</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($loc['is_active'] == 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="location_id" value="<?= $loc['id'] ?>">
                                        <input type="hidden" name="current_status" value="<?= $loc['is_active'] ?>">
                                        <button type="submit" class="btn btn-sm btn-light border py-0 px-2" style="font-size: 0.75rem;">
                                            <?= ($loc['is_active'] == 1) ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal: Add Location -->
<div class="modal fade" id="addLocationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_location">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-geo-alt"></i> Register Campus Facility</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label small fw-semibold text-secondary">Building Name *</label>
                            <input type="text" name="building_name" class="form-control" required placeholder="e.g. Science Complex">
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-semibold text-secondary">Building Code *</label>
                            <input type="text" name="building_code" class="form-control font-monospace" required placeholder="SCI-BLK">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary">Floor Level *</label>
                            <select name="floor" class="form-select" required>
                                <option value="Ground Floor" selected>Ground Floor</option>
                                <option value="1st Floor">1st Floor</option>
                                <option value="2nd Floor">2nd Floor</option>
                                <option value="3rd Floor">3rd Floor</option>
                                <option value="4th Floor">4th Floor</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary">Room / Hall Number</label>
                            <input type="text" name="room_number" class="form-control" placeholder="e.g. Room SC-104">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Area Details</label>
                        <textarea class="form-control" name="area_description" rows="2" placeholder="e.g. Computer programming lab with 45 workstations"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold px-3">Save Location</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once INCLUDES_PATH . 'footer.php'; ?>

