<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = get_db_connection();
    echo "Connected to database: " . DB_NAME . "\n";

    // 1. Check if payments table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'payments'")->fetch();
    if ($tableCheck) {
        echo "Table 'payments' already exists.\n";
    } else {
        echo "Creating 'payments' table...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `payments` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `payment_reference` VARCHAR(60) NOT NULL COMMENT 'Unique payment ref e.g. CF-PAY-2026-0001',
              `receipt_number` VARCHAR(60) NOT NULL COMMENT 'Official printable receipt number e.g. RCP-2026-0001',
              `ticket_id` INT UNSIGNED NOT NULL,
              `user_id` INT UNSIGNED NOT NULL COMMENT 'Student/Reporter payer',
              `amount` DECIMAL(10,2) NOT NULL,
              `currency` VARCHAR(10) NOT NULL DEFAULT 'GHS',
              `gateway` VARCHAR(40) NOT NULL DEFAULT 'paystack_sandbox' COMMENT 'paystack_sandbox, momo_mock, manual_receipt',
              `payment_method` VARCHAR(30) NOT NULL DEFAULT 'mobile_money' COMMENT 'mobile_money, card, bank_transfer',
              `payment_channel` VARCHAR(50) DEFAULT NULL COMMENT 'MTN MoMo, Telecel Cash, AT Money, Visa, Mastercard',
              `payer_phone` VARCHAR(30) DEFAULT NULL,
              `payer_email` VARCHAR(100) DEFAULT NULL,
              `status` ENUM('pending', 'success', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
              `gateway_response` TEXT DEFAULT NULL COMMENT 'Raw gateway verification payload or mock transaction ID',
              `paid_at` DATETIME DEFAULT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_payments_reference` (`payment_reference`),
              UNIQUE KEY `uk_payments_receipt` (`receipt_number`),
              KEY `idx_payments_ticket` (`ticket_id`),
              KEY `idx_payments_user` (`user_id`),
              KEY `idx_payments_status` (`status`),
              CONSTRAINT `fk_payments_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        echo "Table 'payments' created successfully!\n";
    }

    // 2. Check if billing columns exist on tickets
    $colCheck = $pdo->query("SHOW COLUMNS FROM `tickets` LIKE 'billing_amount'")->fetch();
    if (!$colCheck) {
        echo "Adding billing columns to 'tickets' table...\n";
        $pdo->exec("
            ALTER TABLE `tickets`
              ADD COLUMN `billing_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Repair/Material surcharge if billable' AFTER `closed_at`,
              ADD COLUMN `billing_description` VARCHAR(255) DEFAULT NULL COMMENT 'Reason for surcharge (e.g. Replacement Lamp/Cable)' AFTER `billing_amount`,
              ADD COLUMN `payment_status` ENUM('not_required', 'pending', 'paid', 'waived') NOT NULL DEFAULT 'not_required' AFTER `billing_description`,
              ADD KEY `idx_tickets_payment_status` (`payment_status`);
        ");
        echo "Columns added to 'tickets' table!\n";
    } else {
        echo "Billing columns already exist on 'tickets'.\n";
    }

    // 3. Register payment settings in system_settings if table exists
    $settingsCheck = $pdo->query("SHOW TABLES LIKE 'system_settings'")->fetch();
    if ($settingsCheck) {
        echo "Updating payment settings in system_settings...\n";
        $stmt = $pdo->prepare("
            INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`, `description`, `is_public`) 
            VALUES (?, ?, 'payment', ?, 1)
            ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`), `updated_at` = NOW()
        ");
        $settings = [
            ['payment_gateway', 'paystack', 'Default payment gateway provider'],
            ['payment_sandbox_mode', '1', 'If 1, operates in sandbox/test simulation without charging real money'],
            ['paystack_payment_link', 'https://paystack.shop/pay/0fwhj--t6n', 'Direct Paystack payment storefront URL'],
            ['paystack_public_key', 'pk_test_sample_campusfix_key', 'Paystack public test key'],
            ['paystack_secret_key', 'sk_test_sample_campusfix_secret', 'Paystack secret test key'],
            ['currency_code', 'GHS', 'Currency ISO code'],
            ['currency_symbol', 'GH₵', 'Currency display symbol'],
            ['billing_enabled', '1', 'Global toggle to enable maintenance surcharges and payments']
        ];
        foreach ($settings as $s) {
            $stmt->execute($s);
        }
        echo "Payment settings updated!\n";
    }

    // 4. Seed sample tickets if empty
    $countPayments = $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn();
    if ($countPayments == 0) {
        echo "Seeding sample demonstration payment...\n";
        $pdo->exec("
            UPDATE `tickets` 
            SET `billing_amount` = 75.00,
                `billing_description` = 'Replacement HP LaserJet Pickup Roller & Separation Pad (Part #RM2-6303)',
                `payment_status` = 'paid'
            WHERE `id` = 6;

            UPDATE `tickets` 
            SET `billing_amount` = 45.00,
                `billing_description` = 'Safety Breaker & Double Switched Socket Replacement Material',
                `payment_status` = 'pending'
            WHERE `id` = 3;

            INSERT INTO `payments` (`payment_reference`, `receipt_number`, `ticket_id`, `user_id`, `amount`, `currency`, `gateway`, `payment_method`, `payment_channel`, `payer_phone`, `payer_email`, `status`, `gateway_response`, `paid_at`)
            VALUES ('CF-PAY-2026-0001', 'RCP-2026-0001', 6, 7, 75.00, 'GHS', 'paystack_sandbox', 'mobile_money', 'MTN MoMo', '+233267778899', 'michael.stu@campusfix.edu', 'success', '{\"status\":\"success\",\"reference\":\"CF-PAY-2026-0001\",\"amount\":7500,\"currency\":\"GHS\",\"channel\":\"mobile_money\",\"bank\":\"MTN MoMo\",\"simulation\":true}', '2026-11-10 12:30:00');
        ");
        echo "Sample payment seeded!\n";
    }

    echo "\n=== MIGRATION COMPLETED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    echo "MIGRATION ERROR: " . $e->getMessage() . "\n";
}
