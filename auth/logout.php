<?php
/**
 * CampusFix - User Sign Out
 * Destroys active session, cleans cookies, and redirects to login screen.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

$user = current_user();
if ($user) {
    log_activity($user['id'], 'user_logout', 'auth', $user['id']);
}

logout_user();

// Redirect with notice
header("Location: " . BASE_URL . "auth/login.php?logged_out=1");
exit;

