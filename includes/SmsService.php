<?php
/**
 * CampusFix - SMS Gateway & Mock Simulation Engine
 * Integration-ready mobile notification client supporting live gateways and documented mock mode.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../config/database.php';

class SmsService
{
    /**
     * Send an SMS alert to a mobile number
     *
     * @param string $phoneNumber Target recipient e.g. +233241112233
     * @param string $message Text message payload
     * @param int|null $ticketId Associated ticket ID for database logging
     * @param int|null $userId Target user ID
     * @return array ['success' => bool, 'mock' => bool, 'status' => string, 'details' => string]
     */
    public static function sendSms(string $phoneNumber, string $message, ?int $ticketId = null, ?int $userId = null): array
    {
        if (SMS_ENABLED !== 1) {
            return [
                'success' => false,
                'mock'    => false,
                'status'  => 'disabled',
                'details' => 'SMS delivery is disabled in system settings.'
            ];
        }

        $cleanPhone = preg_replace('/[^0-9+]/', '', $phoneNumber);
        if (empty($cleanPhone)) {
            return [
                'success' => false,
                'mock'    => false,
                'status'  => 'invalid_phone',
                'details' => 'Recipient mobile number is invalid or empty.'
            ];
        }

        // 1. Check if Mock Mode is active (default for demonstration without paid credentials)
        if (SMS_MOCK_MODE === 1 || empty(SMS_API_KEY)) {
            $logEntry = sprintf(
                "[%s] [MOCK SMS DISPATCH - SIMULATION ONLY]\nTARGET PHONE: %s\nSENDER ID: %s\nMESSAGE: %s\nNOTE: Message was simulated and not transmitted to live mobile carrier.\n%s\n\n",
                date('Y-m-d H:i:s'),
                $cleanPhone,
                SMS_SENDER_ID,
                $message,
                str_repeat('-', 70)
            );

            $logPath = ROOT_PATH . 'logs/sms_mock.log';
            @file_put_contents($logPath, $logEntry, FILE_APPEND);

            // Record in notifications database as mock_delivered
            if ($userId !== null) {
                try {
                    $pdo = get_db_connection();
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, ticket_id, title, message, channel, status, created_at)
                        VALUES (?, ?, 'SMS Alert (Simulated)', ?, 'sms', 'mock_delivered', NOW())
                    ");
                    $stmt->execute([$userId, $ticketId, "[Mock SMS to {$cleanPhone}]: {$message}"]);
                } catch (Exception $e) {
                    error_log("Failed to insert mock SMS notification: " . $e->getMessage());
                }
            }

            return [
                'success' => true,
                'mock'    => true,
                'status'  => 'mock_delivered',
                'details' => "Simulated SMS to {$cleanPhone} logged to logs/sms_mock.log. No carrier credit consumed."
            ];
        }

        // 2. Production Gateway cURL Transmission (e.g. Hubtel, Arkesel, Twilio)
        try {
            // Sample Hubtel / Arkesel REST API payload structure
            $ch = curl_init();
            $url = "https://api.sms-provider.com/v1/sms/send"; // Configured provider endpoint
            $payload = [
                'sender'  => SMS_SENDER_ID,
                'recipient' => $cleanPhone,
                'content' => $message
            ];

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . SMS_API_KEY
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success' => true,
                    'mock'    => false,
                    'status'  => 'sent',
                    'details' => "SMS successfully transmitted via carrier gateway to {$cleanPhone}."
                ];
            } else {
                throw new Exception("Gateway returned HTTP {$httpCode}: {$response}");
            }

        } catch (Exception $e) {
            error_log("SMS Gateway Transmission Exception: " . $e->getMessage());
            return [
                'success' => false,
                'mock'    => false,
                'status'  => 'failed',
                'details' => "Carrier gateway error: " . $e->getMessage()
            ];
        }
    }
}

