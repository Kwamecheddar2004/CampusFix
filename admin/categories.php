<?php
/**
 * CampusFix - Fault Categories Management
 * Allows administrators to add, inspect, and toggle active maintenance categories.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$user = current_user();
$pageTitle = 'Fault Categories';
$pageHeading = 'Fault Categories Management';
$pageSubtitle = 'Configure service domains, department responsibilities, and badge icons';

$pdo = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
    $action = $_POST['action'] ?? '';

    // Action 1: Add Category
    if ($action === 'add_category') {
        $name       = trim($_POST['name'] ?? '');
        $department = trim($_POST['department_in_charge'] ?? 'General Services');
        $icon       = trim($_POST['icon'] ?? 'bi-gear');
        $colorCode  = trim($_POST['color_code'] ?? '#0d6efd');
        $desc       = trim($_POST['description'] ?? '');

        if (!empty($name)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            $insStmt = $pdo->prepare("
                INSERT INTO categories (name, slug, description, department_in_charge, icon, color_code, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $insStmt->execute([$name, $slug, $desc, $department, $icon, $colorCode]);

            log_activity($user['id'], 'category_created', 'category', (int)$pdo->lastInsertId(), ['name' => $name]);
            flash_message('success', "Category '{$name}' created successfully.");
            header("Location: " . BASE_URL . "admin/categories.php");
            exit;
        }
    }

    // Action 2: Toggle Active Status
    if ($action === 'toggle_active') {
        $catId = (int) ($_POST['category_id'] ?? 0);
        $currStatus = (int) ($_POST['current_status'] ?? 1);
        $newStatus = ($currStatus === 1) ? 0 : 1;

        $upStmt = $pdo->prepare("UPDATE categories SET is_active = ? WHERE id = ?");
        $upStmt->execute([$newStatus, $catId]);

        log_activity($user['id'], 'category_status_toggled', 'category', $catId, ['new_status' => $newStatus]);
        flash_message('success', 'Category status updated.');
        header("Location: " . BASE_URL . "admin/categories.php");
        exit;
    }
}

// Fetch categories with active incident counts
$catStmt = $pdo->query("
    SELECT c.*,
           COUNT(t.id) as total_incidents,
           SUM(CASE WHEN t.status IN ('pending', 'assigned', 'in_progress', 'reopened') THEN 1 ELSE 0 END) as active_incidents
    FROM categories c
    LEFT JOIN tickets t ON c.id = t.category_id
    GROUP BY c.id, c.name, c.slug, c.description, c.department_in_charge, c.icon, c.color_code, c.is_active, c.created_at
    ORDER BY c.name ASC
");
$categories = $catStmt->fetchAll();

include_once INCLUDES_PATH . 'header.php';
include_once INCLUDES_PATH . 'sidebar.php';
?>

<div class="d-flex flex-column flex-1 w-100">
    <?php include_once INCLUDES_PATH . 'navbar.php'; ?>

    <div class="container-fluid px-0">

        <div class="cf-card mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h5 class="cf-card-title mb-1">Campus Incident Categories</h5>
                    <small class="text-muted">Currently managing <?= count($categories) ?> fault classifications</small>
                </div>
                <button type="button" class="btn btn-primary btn-sm fw-semibold shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Category
                </button>
            </div>
        </div>

        <div class="row g-3">
            <?php foreach ($categories as $cat): ?>
                <div class="col-md-6 col-xl-3">
                    <div class="cf-card h-100 mb-0 position-relative">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="p-2 rounded-3 text-white fs-4 d-inline-flex align-items-center justify-content-center" style="background-color: <?= htmlspecialchars($cat['color_code']) ?>; width: 44px; height: 44px;">
                                <i class="bi <?= htmlspecialchars($cat['icon']) ?>"></i>
                            </span>
                            <form method="POST" action="" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                <input type="hidden" name="current_status" value="<?= $cat['is_active'] ?>">
                                <button type="submit" class="badge <?= ($cat['is_active'] == 1) ? 'bg-success-subtle text-success border border-success' : 'bg-secondary-subtle text-secondary' ?> border-0 cursor-pointer" style="font-size: 0.7rem;">
                                    <?= ($cat['is_active'] == 1) ? 'Active' : 'Disabled' ?>
                                </button>
                            </form>
                        </div>

                        <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($cat['name']) ?></h6>
                        <small class="text-muted d-block mb-3" style="font-size: 0.75rem;"><?= htmlspecialchars($cat['department_in_charge']) ?></small>
                        <p class="text-secondary small mb-3" style="font-size: 0.8rem; line-height: 1.4;"><?= htmlspecialchars($cat['description'] ?? 'No description.') ?></p>

                        <div class="pt-2 border-top d-flex justify-content-between align-items-center small text-muted">
                            <span>Open Reports: <strong><?= $cat['active_incidents'] ?></strong></span>
                            <span>Total: <strong><?= $cat['total_incidents'] ?></strong></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<!-- Modal: Add Category -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_category">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-tag"></i> Add Fault Category</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Category Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Elevators & Lifts">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Department in Charge *</label>
                        <input type="text" name="department_in_charge" class="form-control" required placeholder="e.g. Estate & Mechanical Works">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Bootstrap Icon Class</label>
                            <input type="text" name="icon" class="form-control" value="bi-gear" placeholder="e.g. bi-shield-check">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Badge Color Code</label>
                            <input type="color" name="color_code" class="form-control form-control-color w-100" value="#2563eb">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Description</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Brief explanation of items covered under this category..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold px-3">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once INCLUDES_PATH . 'footer.php'; ?>

