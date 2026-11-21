<?php
/**
 * CampusFix - Notification & Mail Configuration
 * Dynamic loader reading settings from database with environment fallback.
 */

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/database.php';

/**
 * Fetch a system configuration value from system_settings table
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function get_system_setting(string $key, $default = '')
{
    static $settingsCache = null;

    if ($settingsCache === null) {
        $settingsCache = [];
        try {
            $pdo = get_db_connection();
            $rows = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll();
            foreach ($rows as $r) {
                $settingsCache[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Exception $e) {
            // Database not ready or table missing
        }
    }

    return $settingsCache[$key] ?? $default;
}

// Mailer Constants
define('MAIL_MAILER', get_system_setting('mail_mailer', 'smtp'));
define('SMTP_HOST', get_system_setting('smtp_host', 'smtp.gmail.com'));
define('SMTP_PORT', (int) get_system_setting('smtp_port', '587'));
define('SMTP_USER', get_system_setting('smtp_user', 'campusfix.alerts@gmail.com'));
define('SMTP_PASS', get_system_setting('smtp_pass', ''));
define('SMTP_ENCRYPTION', get_system_setting('smtp_encryption', 'tls'));
define('MAIL_FROM_ADDRESS', get_system_setting('mail_from_address', 'campusfix.alerts@gmail.com'));
define('MAIL_FROM_NAME', get_system_setting('mail_from_name', 'CampusFix Helpdesk'));
define('MAIL_MOCK_MODE', (int) get_system_setting('mail_mock_mode', '1'));

// SMS Constants
define('SMS_ENABLED', (int) get_system_setting('sms_enabled', '1'));
define('SMS_MOCK_MODE', (int) get_system_setting('sms_mock_mode', '1'));
define('SMS_PROVIDER', get_system_setting('sms_provider', 'hubtel'));
define('SMS_API_KEY', get_system_setting('sms_api_key', ''));
define('SMS_SENDER_ID', get_system_setting('sms_sender_id', 'CampusFix'));

