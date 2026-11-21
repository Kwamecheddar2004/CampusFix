<?php
/**
 * CampusFix - Application Configuration
 * Defines global constants, paths, and environment settings.
 */

// Prevent multiple definitions
if (!defined('CAMPUSFIX_APP')) {
    define('CAMPUSFIX_APP', true);

    // Application Metadata
    define('APP_NAME', 'CampusFix');
    define('APP_TAGLINE', 'Campus Maintenance & IT Fault Management System');
    define('APP_VERSION', '1.0.0');

    // Timezone & Localization
    date_default_timezone_set('UTC');

    // Base Directory Paths
    define('ROOT_PATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
    define('CONFIG_PATH', ROOT_PATH . 'config' . DIRECTORY_SEPARATOR);
    define('INCLUDES_PATH', ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR);
    define('UPLOADS_PATH', ROOT_PATH . 'uploads' . DIRECTORY_SEPARATOR);
    define('TICKET_UPLOADS_PATH', UPLOADS_PATH . 'tickets' . DIRECTORY_SEPARATOR);
    define('AVATAR_UPLOADS_PATH', UPLOADS_PATH . 'avatars' . DIRECTORY_SEPARATOR);

    // Dynamically calculate Base URL for seamless local XAMPP and VirtualHost support
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Determine the web-accessible project root subdirectory
    $docRoot = str_replace(['\\', '/'], '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $currentDir = str_replace(['\\', '/'], '/', ROOT_PATH);
    $subDir = trim(str_replace($docRoot, '', $currentDir), '/');
    
    $baseUrl = $protocol . $host . '/' . ($subDir ? $subDir . '/' : '');
    define('BASE_URL', $baseUrl);

    // Upload & Asset URLs
    define('ASSETS_URL', BASE_URL . 'assets/');
    define('UPLOADS_URL', BASE_URL . 'uploads/');
    define('TICKET_UPLOADS_URL', UPLOADS_URL . 'tickets/');
    define('AVATAR_UPLOADS_URL', UPLOADS_URL . 'avatars/');

    // Security & Session Settings
    define('SESSION_NAME', 'CAMPUSFIX_SESSION');
    define('SESSION_LIFETIME', 86400); // 24 hours
    define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 Megabytes
    define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);
    define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

    // SLA Turnaround Deadlines (in Hours)
    define('SLA_URGENT_HOURS', 4);
    define('SLA_HIGH_HOURS', 12);
    define('SLA_MEDIUM_HOURS', 24);
    define('SLA_LOW_HOURS', 72);
}

