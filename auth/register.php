<?php
/**
 * CampusFix - Student Registration
 * Creates authenticated student accounts with bcrypt hashing and validation.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    $role = current_user()['role'];
    header("Location: " . BASE_URL . "{$role}/dashboard.php");
    exit;
}

$error = '';
$success = '';
$fullName = '';
$email = '';
$phone = '';
$studentId = '';
$department = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();

    $fullName   = trim($_POST['full_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $studentId  = trim($_POST['student_id'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($fullName) || empty($email) || empty($password) || empty($department)) {
        $error = 'Please fill in all mandatory fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid university email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPass) {
        $error = 'Passwords do not match. Please re-enter.';
    } else {
        try {
            $pdo = get_db_connection();

            // Check if email already registered
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $checkStmt->execute([$email]);
            if ($checkStmt->fetch()) {
                $error = 'An account with this email address already exists. Please log in.';
            } else {
                // Generate user code if studentId empty
                $userCode = !empty($studentId) ? $studentId : 'STU-' . date('Y') . '-' . rand(100, 999);
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

                $insertStmt = $pdo->prepare("
                    INSERT INTO users (user_code, full_name, email, phone, password_hash, role, department, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'student', ?, 'active', NOW())
                ");
                $insertStmt->execute([$userCode, $fullName, $email, $phone, $hash, $department]);
                $newUserId = (int) $pdo->lastInsertId();

                log_activity($newUserId, 'user_registered', 'users', $newUserId, ['role' => 'student']);

                // Auto-login newly registered student
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'          => $newUserId,
                    'user_code'   => $userCode,
                    'full_name'   => $fullName,
                    'email'       => $email,
                    'role'        => 'student',
                    'department'  => $department,
                    'avatar'      => null
                ];

                flash_message('success', 'Your student account has been created successfully! Welcome to CampusFix.');
                header("Location: " . BASE_URL . "student/dashboard.php");
                exit;
            }
        } catch (Exception $e) {
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration | CampusFix</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/style.css">

    <style>
        body {
            background-color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .register-card {
            max-width: 520px;
            width: 100%;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08);
            padding: 2.25rem;
        }
    </style>
</head>
<body>

    <div class="register-card">
        <div class="text-center mb-4">
            <a href="<?= BASE_URL ?>" class="d-inline-flex align-items-center gap-2 text-decoration-none mb-2">
                <span class="bg-primary text-white p-2 rounded-3 d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="bi bi-tools fs-5"></i>
                </span>
                <span class="fs-4 fw-bold text-dark">CampusFix</span>
            </a>
            <h5 class="fw-bold text-dark mb-1">Create Student Account</h5>
            <p class="text-muted small">Report faults, track maintenance and confirm resolutions</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="needs-validation" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="full_name" class="form-label small fw-semibold text-secondary">Full Name *</label>
                <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($fullName) ?>" placeholder="e.g. Kwame Bonsu" required>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label for="email" class="form-label small fw-semibold text-secondary">University Email *</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="student@st.ug.edu.gh" required>
                </div>
                <div class="col-md-6">
                    <label for="student_id" class="form-label small fw-semibold text-secondary">Student ID Number</label>
                    <input type="text" class="form-control" id="student_id" name="student_id" value="<?= htmlspecialchars($studentId) ?>" placeholder="10982345">
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label for="department" class="form-label small fw-semibold text-secondary">Department / School *</label>
                    <select class="form-select" id="department" name="department" required>
                        <option value="">Choose department...</option>
                        <option value="Computer Science Dept" <?= ($department === 'Computer Science Dept') ? 'selected' : '' ?>>Computer Science</option>
                        <option value="Electrical Engineering" <?= ($department === 'Electrical Engineering') ? 'selected' : '' ?>>Electrical Engineering</option>
                        <option value="Business Administration" <?= ($department === 'Business Administration') ? 'selected' : '' ?>>Business Administration</option>
                        <option value="School of Law" <?= ($department === 'School of Law') ? 'selected' : '' ?>>School of Law</option>
                        <option value="Faculty of Arts" <?= ($department === 'Faculty of Arts') ? 'selected' : '' ?>>Faculty of Arts</option>
                        <option value="General Campus Staff" <?= ($department === 'General Campus Staff') ? 'selected' : '' ?>>Staff / Other</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label small fw-semibold text-secondary">Mobile Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($phone) ?>" placeholder="+233 24 000 0000">
                </div>
            </div>

            <div class="row g-2 mb-4">
                <div class="col-md-6">
                    <label for="password" class="form-label small fw-semibold text-secondary">Password *</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Min. 8 characters" required>
                </div>
                <div class="col-md-6">
                    <label for="confirm_password" class="form-label small fw-semibold text-secondary">Confirm Password *</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Re-type password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                <i class="bi bi-person-plus-fill me-1"></i> Register Student Account
            </button>
        </form>

        <div class="text-center mt-4">
            <p class="text-muted small mb-0">
                Already registered? <a href="<?= BASE_URL ?>auth/login.php" class="fw-semibold text-primary text-decoration-none">Sign In Here</a>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ASSETS_URL ?>js/main.js"></script>
</body>
</html>

