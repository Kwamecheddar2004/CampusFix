<?php
/**
 * CampusFix - Authentication, Session Security & RBAC Guards
 * Enforces role restrictions, session hardening, and CSRF token verification.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Hardened session initialization
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_name(SESSION_NAME);
    @session_start();
}

/**
 * Check if a user is currently logged in
 *
 * @return bool
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Retrieve the currently logged-in user profile array or null
 *
 * @return array|null
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Require user authentication; redirects to login if not logged in
 *
 * @param string|null $redirectUrl Optional URL to return to after successful login
 * @return void
 */
function require_login(?string $redirectUrl = null): void
{
    if (!is_logged_in()) {
        $target = $redirectUrl ?: ($_SERVER['REQUEST_URI'] ?? '');
        $param = !empty($target) ? '?redirect=' . urlencode($target) : '';
        flash_message('warning', 'Please log in to access this page.');
        header('Location: ' . BASE_URL . 'auth/login.php' . $param);
        exit;
    }
}

/**
 * Require a specific user role or array of allowed roles (RBAC)
 *
 * @param string|array $allowedRoles Role(s) permitted: 'student', 'technician', 'admin'
 * @return void
 */
function require_role($allowedRoles): void
{
    require_login();

    $user = current_user();
    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];

    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        include_once INCLUDES_PATH . '403.php';
        exit;
    }
}

/**
 * Generate or retrieve CSRF token for forms
 *
 * @return string
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generate a hidden HTML input containing the CSRF token
 *
 * @return string
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify submitted CSRF token
 *
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token = null): bool
{
    $token = $token ?? ($_POST['csrf_token'] ?? '');
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Enforce CSRF verification on POST requests
 *
 * @return void
 */
function enforce_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf_token()) {
            http_response_code(403);
            die('CSRF token validation failed. Please refresh the page and try again.');
        }
    }
}

/**
 * Sanitize strings or recursive arrays against XSS
 *
 * @param mixed $data
 * @return mixed
 */
function sanitize($data)
{
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Store a session flash message
 *
 * @param string $type success | danger | warning | info
 * @param string $message
 * @return void
 */
function flash_message(string $type, string $message): void
{
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Retrieve and clear all flash messages
 *
 * @return array
 */
function get_flash_messages(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Log out user and destroy session completely
 *
 * @return void
 */
function logout_user(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }
}

