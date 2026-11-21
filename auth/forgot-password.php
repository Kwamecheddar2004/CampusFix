<?php
/**
 * CampusFix - Password Recovery (Simulation)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

$message = '';
$simulatedLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
    $email = trim($_POST['email'] ?? '');

    if (!empty($email)) {
        $token = bin2hex(random_bytes(16));
        $simulatedLink = BASE_URL . "auth/login.php?reset_token=" . $token;
        $message = "In production, an automated password reset email with secure verification link is dispatched to {$email}. For demonstration purposes, your mock reset link has been generated below.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | CampusFix</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/style.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 p-3">
    <div class="card p-4 shadow-sm border" style="max-width: 440px; width: 100%;">
        <div class="text-center mb-3">
            <h4 class="fw-bold text-dark">Password Recovery</h4>
            <p class="text-muted small">Enter your university email address to receive reset instructions.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-info small mb-3">
                <i class="bi bi-info-circle-fill me-1"></i> <?= htmlspecialchars($message) ?>
                <div class="mt-2 p-2 bg-white rounded border text-break font-monospace small">
                    <a href="<?= $simulatedLink ?>" class="text-primary"><?= $simulatedLink ?></a>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="email" class="form-label small fw-semibold text-secondary">University Email</label>
                <input type="email" class="form-control" id="email" name="email" required placeholder="user@campusfix.edu">
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                <i class="bi bi-send me-1"></i> Send Recovery Link
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="<?= BASE_URL ?>auth/login.php" class="text-secondary small text-decoration-none">
                <i class="bi bi-arrow-left"></i> Return to Sign In
            </a>
        </div>
    </div>
</body>
</html>

