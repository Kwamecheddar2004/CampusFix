<?php
/**
 * CampusFix - Student Profile Management
 * Allows students to update contact details and change passwords.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('student');

$user = current_user();
$pageTitle = 'Student Profile';
$pageHeading = 'Account Settings';
$pageSubtitle = 'Manage your student profile, contact numbers, and security credentials';

$error = '';
$success = '';

$pdo = get_db_connection();

// Refresh current user data from DB
$uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->execute([$user['id']]);
$userData = $uStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
    $action = $_POST['action'] ?? '';

    // Action 1: Update Profile Details
    if ($action === 'update_profile') {
        $fullName   = trim($_POST['full_name'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');

        if (empty($fullName) || empty($department)) {
            $error = 'Full Name and Department cannot be empty.';
        } else {
            $upStmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, department = ?, updated_at = NOW() WHERE id = ?");
            $upStmt->execute([$fullName, $phone, $department, $user['id']]);

            // Update session
            $_SESSION['user']['full_name'] = $fullName;
            $_SESSION['user']['department'] = $department;

            log_activity($user['id'], 'profile_updated', 'user', $user['id']);
            flash_message('success', 'Profile details updated successfully.');
            header("Location: " . BASE_URL . "student/profile.php");
            exit;
        }
    }

    // Action 2: Change Password
    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPass, $userData['password_hash'])) {
            $error = 'Your current password was entered incorrectly.';
        } elseif (strlen($newPass) < 8) {
            $error = 'New password must be at least 8 characters long.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'New passwords do not match.';
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 10]);
            $pwdStmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $pwdStmt->execute([$newHash, $user['id']]);

            log_activity($user['id'], 'password_changed', 'user', $user['id']);
            flash_message('success', 'Your password has been changed successfully.');
            header("Location: " . BASE_URL . "student/profile.php");
            exit;
        }
    }
}

include_once INCLUDES_PATH . 'header.php';
include_once INCLUDES_PATH . 'sidebar.php';
?>

<div class="d-flex flex-column flex-1 w-100">
    <?php include_once INCLUDES_PATH . 'navbar.php'; ?>

    <div class="container-fluid px-0">
        <div class="row g-4 justify-content-center">

            <!-- Profile Sidebar Card -->
            <div class="col-lg-4 col-md-5">
                <div class="cf-card text-center">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center fs-1 fw-bold mb-3 shadow-sm" style="width: 80px; height: 80px;">
                        <?= strtoupper(substr($userData['full_name'], 0, 1)) ?>
                    </div>
                    <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($userData['full_name']) ?></h5>
                    <p class="text-muted small mb-2"><?= htmlspecialchars($userData['email']) ?></p>
                    <span class="badge bg-primary-subtle text-primary border border-primary text-uppercase px-2 py-1">
                        <?= htmlspecialchars($userData['role']) ?>
                    </span>

                    <div class="border-top pt-3 mt-4 text-start small">
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted">Student Code:</span>
                            <strong class="font-monospace text-dark"><?= htmlspecialchars($userData['user_code']) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted">Department:</span>
                            <span class="text-dark fw-medium"><?= htmlspecialchars($userData['department'] ?? 'General') ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted">Phone:</span>
                            <span class="text-dark font-monospace"><?= htmlspecialchars($userData['phone'] ?? 'None') ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted">Registered:</span>
                            <span class="text-dark"><?= date('M j, Y', strtotime($userData['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Forms -->
            <div class="col-lg-7 col-md-7">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Form 1: Profile Information -->
                <div class="cf-card mb-4">
                    <h5 class="cf-card-title mb-3">Personal & Academic Information</h5>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_profile">

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($userData['full_name']) ?>" required>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-secondary">University Email</label>
                                <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($userData['email']) ?>" readonly disabled>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-secondary">Mobile Phone Number</label>
                                <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" placeholder="+233 24 000 0000">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Department / School</label>
                            <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($userData['department'] ?? '') ?>" required>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary btn-sm fw-semibold px-3">
                                Save Profile Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Form 2: Change Password -->
                <div class="cf-card">
                    <h5 class="cf-card-title mb-3">Security & Password</h5>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required placeholder="••••••••">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-secondary">New Password</label>
                                <input type="password" name="new_password" class="form-control" required placeholder="Min 8 characters">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-secondary">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required placeholder="Re-type new password">
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-outline-danger btn-sm fw-semibold px-3">
                                <i class="bi bi-key-fill me-1"></i> Update Password
                            </button>
                        </div>
                    </form>
                </div>

            </div>

        </div>
    </div>
</div>

<?php include_once INCLUDES_PATH . 'footer.php'; ?>

