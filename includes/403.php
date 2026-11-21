<?php
/**
 * CampusFix - 403 Forbidden Error Page
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth_guard.php';

$user = current_user();
$homeLink = BASE_URL;

if ($user) {
    if ($user['role'] === 'admin') $homeLink .= 'admin/dashboard.php';
    elseif ($user['role'] === 'technician') $homeLink .= 'technician/dashboard.php';
    else $homeLink .= 'student/dashboard.php';
} else {
    $homeLink .= 'auth/login.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden | CampusFix</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/style.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
    <div class="text-center p-4" style="max-width: 520px;">
        <div class="text-danger mb-3" style="font-size: 4rem;">
            <i class="bi bi-shield-x"></i>
        </div>
        <h1 class="fw-bold text-dark mb-2">403 Forbidden</h1>
        <h5 class="fw-semibold text-secondary mb-3">Access Denied</h5>
        <p class="text-muted mb-4">
            You do not possess the required administrative or role privileges to view this section of the CampusFix portal. All access attempts are recorded in the security audit ledger.
        </p>
        <a href="<?= $homeLink ?>" class="btn btn-primary px-4 py-2 fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Return to Authorized Dashboard
        </a>
    </div>
</body>
</html>

