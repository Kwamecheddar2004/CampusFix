<?php
/**
 * CampusFix - User Authentication (Login)
 * Validates credentials via password_verify() and redirects based on RBAC role.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

// If already logged in, redirect to respective role dashboard
if (is_logged_in()) {
    $role = current_user()['role'];
    header("Location: " . BASE_URL . "{$role}/dashboard.php");
    exit;
}

$error = '';
$email = '';
$redirect = $_GET['redirect'] ?? '';

// Handle POST Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } else {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT id, user_code, full_name, email, password_hash, role, department, status, avatar FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] !== 'active') {
                    $error = 'Your account has been deactivated or suspended. Please contact the administrator.';
                } else {
                    // Regenerate session ID for security against session fixation
                    session_regenerate_id(true);

                    // Store safe session payload
                    $_SESSION['user'] = [
                        'id'          => (int) $user['id'],
                        'user_code'   => $user['user_code'],
                        'full_name'   => $user['full_name'],
                        'email'       => $user['email'],
                        'role'        => $user['role'],
                        'department'  => $user['department'],
                        'avatar'      => $user['avatar']
                    ];

                    // Audit Log
                    log_activity($user['id'], 'user_login', 'auth', $user['id'], ['email' => $email]);

                    flash_message('success', "Welcome back, {$user['full_name']}!");

                    // Role-Based Redirection
                    if (!empty($redirect) && str_starts_with($redirect, '/')) {
                        header("Location: " . $redirect);
                    } else {
                        switch ($user['role']) {
                            case 'admin':
                                header("Location: " . BASE_URL . "admin/dashboard.php");
                                break;
                            case 'technician':
                                header("Location: " . BASE_URL . "technician/dashboard.php");
                                break;
                            default:
                                header("Location: " . BASE_URL . "student/dashboard.php");
                                break;
                        }
                    }
                    exit;
                }
            } else {
                $error = 'Invalid email address or password. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'Database connection error. Please ensure MySQL is running in XAMPP.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | CampusFix</title>
    
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
        .auth-card {
            max-width: 440px;
            width: 100%;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08);
            padding: 2.25rem;
        }
        .demo-pill {
            cursor: pointer;
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
            border: 1px dashed #cbd5e1;
            background: #f8fafc;
            color: #334155;
            transition: all 0.15s ease;
        }
        .demo-pill:hover {
            border-color: #2563eb;
            background: #eff6ff;
            color: #1d4ed8;
        }
    </style>
</head>
<body>

    <div class="auth-card">
        <!-- Logo & Title -->
        <div class="text-center mb-4">
            <a href="<?= BASE_URL ?>" class="d-inline-flex align-items-center gap-2 text-decoration-none mb-2">
                <span class="bg-primary text-white p-2 rounded-3 d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="bi bi-tools fs-5"></i>
                </span>
                <span class="fs-4 fw-bold text-dark">CampusFix</span>
            </a>
            <h5 class="fw-bold text-dark mb-1">Sign in to your account</h5>
            <p class="text-muted small">Campus Maintenance & IT Fault Portal</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="" class="needs-validation" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="email" class="form-label small fw-semibold text-secondary">University Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control border-start-0" id="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="user@campusfix.edu" required autofocus>
                </div>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="password" class="form-label small fw-semibold text-secondary mb-0">Password</label>
                    <a href="<?= BASE_URL ?>auth/forgot-password.php" class="text-primary text-decoration-none small">Forgot?</a>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="••••••••" required>
                </div>
            </div>

            <div class="mb-4 d-flex align-items-center justify-content-between">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="remember">
                    <label class="form-check-label small text-muted" for="remember">Remember me</label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
            </button>
        </form>

        <!-- Demo Quick-Fill Pills -->
        <div class="mt-4 pt-3 border-top">
            <div class="text-muted small fw-semibold mb-2 text-center" style="font-size: 0.75rem;">
                <i class="bi bi-lightning-charge-fill text-warning"></i> QUICK DEMO LOGIN (1-CLICK)
            </div>
            <div class="d-flex flex-wrap gap-2 justify-content-center">
                <button type="button" class="btn-autofill demo-pill" data-email="admin@campusfix.edu" data-password="Admin@123">
                    🛡️ Admin
                </button>
                <button type="button" class="btn-autofill demo-pill" data-email="samuel.it@campusfix.edu" data-password="Tech@123">
                    🔧 IT Tech
                </button>
                <button type="button" class="btn-autofill demo-pill" data-email="akua.electric@campusfix.edu" data-password="Tech@123">
                    ⚡ Electrician
                </button>
                <button type="button" class="btn-autofill demo-pill" data-email="emmanuel.stu@campusfix.edu" data-password="Student@123">
                    🎓 Student
                </button>
            </div>
        </div>

        <div class="text-center mt-4">
            <p class="text-muted small mb-0">
                Don't have an account? <a href="<?= BASE_URL ?>auth/register.php" class="fw-semibold text-primary text-decoration-none">Sign Up (Students)</a>
            </p>
        </div>
    </div>

    <!-- Bootstrap & Custom JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ASSETS_URL ?>js/main.js"></script>
</body>
</html>

