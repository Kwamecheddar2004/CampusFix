<?php
/**
 * CampusFix - Administrator User & Staff Management
 * Manage students, technicians, and administrators; toggle active status; create staff accounts.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$user = current_user();
$pageTitle = 'User Management';
$pageHeading = 'User & Staff Management';
$pageSubtitle = 'Manage campus students, certified technicians, and administrative roles';

$roleFilter = strtolower(trim($_GET['role'] ?? 'all'));
$search = trim($_GET['search'] ?? '');

$pdo = get_db_connection();

// Handle Actions (Toggle status, Create staff)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
    $action = $_POST['action'] ?? '';

    // Action 1: Toggle Active / Suspended
    if ($action === 'toggle_status') {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $newStatus    = trim($_POST['new_status'] ?? 'active');

        if ($targetUserId !== $user['id'] && in_array($newStatus, ['active', 'suspended', 'inactive'], true)) {
            $togStmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
            $togStmt->execute([$newStatus, $targetUserId]);

            log_activity($user['id'], 'user_status_changed', 'user', $targetUserId, ['new_status' => $newStatus]);
            flash_message('success', 'User account status updated.');
            header("Location: " . BASE_URL . "admin/users.php?role=" . urlencode($roleFilter));
            exit;
        }
    }

    // Action 2: Create New Technician or Admin
    if ($action === 'create_user') {
        $fullName   = trim($_POST['full_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $role       = trim($_POST['role'] ?? 'technician');
        $department = trim($_POST['department'] ?? '');
        $password   = $_POST['password'] ?? '';

        if (empty($fullName) || empty($email) || empty($password) || empty($role)) {
            flash_message('danger', 'Please fill in all mandatory fields.');
        } else {
            // Check if email exists
            $chkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $chkStmt->execute([$email]);
            if ($chkStmt->fetch()) {
                flash_message('danger', 'A user with this email address already exists.');
            } else {
                $prefix = ($role === 'technician') ? 'TECH' : (($role === 'admin') ? 'ADM' : 'STU');
                $userCode = $prefix . '-' . date('Y') . '-' . rand(100, 999);
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

                $insStmt = $pdo->prepare("
                    INSERT INTO users (user_code, full_name, email, phone, password_hash, role, department, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                $insStmt->execute([$userCode, $fullName, $email, $phone, $hash, $role, $department]);
                $newId = (int) $pdo->lastInsertId();

                log_activity($user['id'], 'staff_created', 'user', $newId, ['role' => $role, 'user_code' => $userCode]);
                flash_message('success', ucfirst($role) . " account created for {$fullName} ({$userCode})!");
                header("Location: " . BASE_URL . "admin/users.php?role=" . urlencode($role));
                exit;
            }
        }
    }
}

// Fetch users
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($roleFilter !== 'all' && in_array($roleFilter, ['student', 'technician', 'admin'], true)) {
    $sql .= " AND role = ?";
    $params[] = $roleFilter;
}

if (!empty($search)) {
    $sql .= " AND (full_name LIKE ? OR email LIKE ? OR user_code LIKE ? OR department LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY id DESC";
$uStmt = $pdo->prepare($sql);
$uStmt->execute($params);
$usersList = $uStmt->fetchAll();

include_once INCLUDES_PATH . 'header.php';
include_once INCLUDES_PATH . 'sidebar.php';
?>

<div class="d-flex flex-column flex-1 w-100">
    <?php include_once INCLUDES_PATH . 'navbar.php'; ?>

    <div class="container-fluid px-0">

        <!-- Toolbar & Add Staff Button -->
        <div class="cf-card mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <h5 class="cf-card-title mb-1">User Directory</h5>
                    <small class="text-muted">Registered university accounts</small>
                </div>
                <button type="button" class="btn btn-primary btn-sm fw-semibold shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-person-plus-fill me-1"></i> Add Technician / Staff
                </button>
            </div>

            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, email, user code, department...">
                    </div>
                </div>

                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="role" onchange="this.form.submit()">
                        <option value="all" <?= ($roleFilter === 'all') ? 'selected' : '' ?>>All Roles</option>
                        <option value="student" <?= ($roleFilter === 'student') ? 'selected' : '' ?>>Students / Reporters</option>
                        <option value="technician" <?= ($roleFilter === 'technician') ? 'selected' : '' ?>>Technicians</option>
                        <option value="admin" <?= ($roleFilter === 'admin') ? 'selected' : '' ?>>Administrators</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-light btn-sm w-100 border text-muted">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="cf-card">
            <div class="table-responsive">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>User Code</th>
                            <th>Name & Email</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usersList as $u): ?>
                            <tr>
                                <td><code class="fw-bold"><?= htmlspecialchars($u['user_code']) ?></code></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                            <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark small"><?= htmlspecialchars($u['full_name']) ?></div>
                                            <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($u['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $badge = ($u['role'] === 'admin') ? 'bg-danger-subtle text-danger border border-danger' : 
                                            (($u['role'] === 'technician') ? 'bg-primary-subtle text-primary border border-primary' : 'bg-secondary-subtle text-secondary');
                                    ?>
                                    <span class="badge <?= $badge ?> px-2 py-1 text-uppercase" style="font-size: 0.7rem;">
                                        <?= htmlspecialchars($u['role']) ?>
                                    </span>
                                </td>
                                <td class="small text-dark"><?= htmlspecialchars($u['department'] ?? 'General') ?></td>
                                <td class="small text-secondary font-monospace"><?= htmlspecialchars($u['phone'] ?? 'None') ?></td>
                                <td>
                                    <?php if ($u['status'] === 'active'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                                <td class="text-end">
                                    <?php if ($u['id'] !== $user['id']): ?>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="new_status" value="<?= ($u['status'] === 'active') ? 'suspended' : 'active' ?>">
                                            <button type="submit" class="btn btn-sm <?= ($u['status'] === 'active') ? 'btn-outline-danger' : 'btn-outline-success' ?> py-0 px-2" style="font-size: 0.75rem;">
                                                <?= ($u['status'] === 'active') ? 'Suspend' : 'Activate' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">You</span>
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

<!-- Modal: Add Technician / Admin -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_user">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus"></i> Add New Staff Member</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" required placeholder="e.g. Samuel Osei">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">University Email *</label>
                            <input type="email" name="email" class="form-control" required placeholder="staff@campusfix.edu">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Role *</label>
                            <select name="role" class="form-select" required>
                                <option value="technician" selected>Technician</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Department / Unit *</label>
                            <input type="text" name="department" class="form-control" required placeholder="e.g. IT Directorate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" placeholder="+233 24 000 0000">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Initial Password *</label>
                        <input type="password" name="password" class="form-control" required placeholder="Min 8 characters">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold px-3">Create Staff Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once INCLUDES_PATH . 'footer.php'; ?>

