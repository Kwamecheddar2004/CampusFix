<?php
/**
 * CampusFix - Payment Gateway & Surcharge Engine (Paystack Sandbox & Mobile Money)
 * Handles maintenance surcharges, material fees, sandbox simulation, and live verification.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/SmsService.php';

class PaymentService
{
    /**
     * Get a payment configuration value
     */
    public static function getSetting(string $key, $default = '')
    {
        return get_system_setting($key, $default);
    }

    public static function isSandbox(): bool
    {
        return (int) self::getSetting('payment_sandbox_mode', '1') === 1;
    }

    public static function getPublicKey(): string
    {
        return self::getSetting('paystack_public_key', 'pk_test_campusfix_public_sandbox');
    }

    public static function getCurrencySymbol(): string
    {
        return self::getSetting('currency_symbol', 'GH₵');
    }

    public static function getCurrencyCode(): string
    {
        return self::getSetting('currency_code', 'GHS');
    }

    /**
     * Attach a billable maintenance surcharge to a ticket (Admin / Tech)
     */
    public static function attachSurcharge(int $ticketId, float $amount, string $description, int $actorId): bool
    {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            UPDATE tickets 
            SET billing_amount = ?, billing_description = ?, payment_status = 'pending', updated_at = NOW()
            WHERE id = ?
        ");
        $success = $stmt->execute([$amount, $description, $ticketId]);

        if ($success) {
            // Fetch ticket and reporter info
            $tStmt = $pdo->prepare("
                SELECT t.ticket_number, t.reporter_id, u.phone, u.full_name 
                FROM tickets t 
                JOIN users u ON t.reporter_id = u.id 
                WHERE t.id = ?
            ");
            $tStmt->execute([$ticketId]);
            $ticket = $tStmt->fetch();

            if ($ticket) {
                $currency = self::getCurrencySymbol();
                $formattedAmount = number_format($amount, 2);

                // Notify reporter
                $notifTitle = "Payment Surcharge Attached";
                $notifMsg = "A maintenance surcharge of {$currency} {$formattedAmount} was attached to ticket {$ticket['ticket_number']} for: {$description}. Please view ticket to pay online.";

                $nStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, ticket_id, title, message, channel, status, created_at)
                    VALUES (?, ?, ?, ?, 'in_app', 'sent', NOW())
                ");
                $nStmt->execute([$ticket['reporter_id'], $ticketId, $notifTitle, $notifMsg]);

                // Also trigger SMS notification if phone is available
                if (!empty($ticket['phone'])) {
                    $smsMsg = "CampusFix: Surcharge of {$currency} {$formattedAmount} attached to ticket {$ticket['ticket_number']}. Log in to pay via Mobile Money/Card.";
                    SmsService::sendSms($ticket['phone'], $smsMsg, $ticketId, $ticket['reporter_id']);
                }

                // Log activity
                log_activity($actorId, 'surcharge_attached', 'ticket', $ticketId, [
                    'amount'      => $amount,
                    'description' => $description
                ]);
            }
        }

        return $success;
    }

    /**
     * Waive a surcharge (Admin only)
     */
    public static function waiveSurcharge(int $ticketId, int $adminId, string $reason = ''): bool
    {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            UPDATE tickets 
            SET payment_status = 'waived', updated_at = NOW() 
            WHERE id = ?
        ");
        $success = $stmt->execute([$ticketId]);

        if ($success) {
            log_activity($adminId, 'surcharge_waived', 'ticket', $ticketId, ['reason' => $reason]);
        }
        return $success;
    }

    /**
     * Initialize a payment transaction and generate unique references
     */
    public static function initializePayment(int $ticketId, int $userId, float $amount, string $email, string $phone, string $channel = 'mobile_money', string $provider = 'paystack_sandbox'): array
    {
        $pdo = get_db_connection();

        // Generate unique payment reference and receipt number
        $uniqueSuffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $reference = sprintf("CF-PAY-%s-%s", date('Y'), $uniqueSuffix);
        $receiptNumber = sprintf("RCP-%s-%s", date('Y'), $uniqueSuffix);
        $currency = self::getCurrencyCode();

        // Check if a pending payment already exists for this ticket
        $checkStmt = $pdo->prepare("SELECT id FROM payments WHERE ticket_id = ? AND status = 'pending'");
        $checkStmt->execute([$ticketId]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            // Update existing pending payment
            $upStmt = $pdo->prepare("
                UPDATE payments 
                SET payment_reference = ?, receipt_number = ?, amount = ?, currency = ?, gateway = ?, 
                    payment_method = ?, payer_phone = ?, payer_email = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $upStmt->execute([$reference, $receiptNumber, $amount, $currency, $provider, $channel, $phone, $email, $existing['id']]);
        } else {
            // Insert new payment record
            $insStmt = $pdo->prepare("
                INSERT INTO payments (payment_reference, receipt_number, ticket_id, user_id, amount, currency, gateway, payment_method, payer_phone, payer_email, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $insStmt->execute([$reference, $receiptNumber, $ticketId, $userId, $amount, $currency, $provider, $channel, $phone, $email]);
        }

        return [
            'success'        => true,
            'reference'      => $reference,
            'receipt_number' => $receiptNumber,
            'amount_pesewas' => (int) round($amount * 100),
            'amount_decimal' => $amount,
            'currency'       => $currency,
            'public_key'     => self::getPublicKey(),
            'is_sandbox'     => self::isSandbox()
        ];
    }

    /**
     * Complete and verify payment (supports Sandbox simulation and Paystack Live Verification API)
     */
    public static function verifyPayment(string $reference, array $extraMeta = []): array
    {
        $pdo = get_db_connection();

        // Find payment record
        $stmt = $pdo->prepare("
            SELECT p.*, t.ticket_number, t.title as ticket_title, t.reporter_id, u.full_name as payer_name 
            FROM payments p
            JOIN tickets t ON p.ticket_id = t.id
            JOIN users u ON p.user_id = u.id
            WHERE p.payment_reference = ?
        ");
        $stmt->execute([$reference]);
        $payment = $stmt->fetch();

        if (!$payment) {
            return [
                'success' => false,
                'status'  => 'not_found',
                'message' => "Payment reference '{$reference}' not found in records."
            ];
        }

        if ($payment['status'] === 'success') {
            return [
                'success'        => true,
                'status'         => 'already_paid',
                'receipt_number' => $payment['receipt_number'],
                'message'        => 'Payment was already verified and receipt generated.'
            ];
        }

        $isSandbox = self::isSandbox();
        $secretKey = self::getSetting('paystack_secret_key', '');
        $gatewayResponse = [];

        // 1. Live Paystack Gateway API Verification (if online and live secret provided)
        if (!$isSandbox && !empty($secretKey)) {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/verify/" . rawurlencode($reference));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer " . $secretKey,
                    "Cache-Control: no-cache",
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 12);
                $respRaw = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $respJson = json_decode($respRaw, true);
                if ($httpCode === 200 && isset($respJson['data']['status']) && $respJson['data']['status'] === 'success') {
                    $gatewayResponse = $respJson['data'];
                } else {
                    return [
                        'success' => false,
                        'status'  => 'gateway_rejected',
                        'message' => $respJson['message'] ?? 'Transaction was not successful on payment gateway.'
                    ];
                }
            } catch (Exception $e) {
                error_log("Paystack Verification cURL Error: " . $e->getMessage());
                return [
                    'success' => false,
                    'status'  => 'curl_error',
                    'message' => 'Failed to reach payment gateway: ' . $e->getMessage()
                ];
            }
        } else {
            // 2. High-Fidelity Sandbox / Mobile Money Simulator Verification
            $selectedChannel = $extraMeta['channel'] ?? 'MTN MoMo';
            $gatewayResponse = [
                'status'        => 'success',
                'reference'     => $reference,
                'amount'        => (int) round($payment['amount'] * 100),
                'currency'      => $payment['currency'],
                'channel'       => $selectedChannel,
                'gateway'       => 'paystack_sandbox',
                'simulation'    => true,
                'paid_at'       => date('Y-m-d H:i:s'),
                'customer'      => [
                    'email' => $payment['payer_email'],
                    'phone' => $payment['payer_phone']
                ]
            ];
        }

        // Update payment record to success
        $channelName = $extraMeta['channel'] ?? ($gatewayResponse['channel'] ?? 'Mobile Money');
        $upStmt = $pdo->prepare("
            UPDATE payments 
            SET status = 'success', payment_channel = ?, gateway_response = ?, paid_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $upStmt->execute([$channelName, json_encode($gatewayResponse), $payment['id']]);

        // Update ticket payment status to paid
        $ticketStmt = $pdo->prepare("
            UPDATE tickets 
            SET payment_status = 'paid', updated_at = NOW() 
            WHERE id = ?
        ");
        $ticketStmt->execute([$payment['ticket_id']]);

        // Audit Trail in Ticket Status History
        $histStmt = $pdo->prepare("
            INSERT INTO ticket_status_history (ticket_id, old_status, new_status, changed_by, notes, created_at)
            VALUES (?, (SELECT status FROM tickets WHERE id = ?), (SELECT status FROM tickets WHERE id = ?), ?, ?, NOW())
        ");
        $auditNote = sprintf(
            "Payment of %s %.2f confirmed via %s (Ref: %s, Receipt: %s)",
            self::getCurrencySymbol(),
            $payment['amount'],
            $channelName,
            $reference,
            $payment['receipt_number']
        );
        $histStmt->execute([$payment['ticket_id'], $payment['ticket_id'], $payment['ticket_id'], $payment['user_id'], $auditNote]);

        // Add Notification
        $currency = self::getCurrencySymbol();
        $notifMsg = "Payment of {$currency} " . number_format($payment['amount'], 2) . " for ticket {$payment['ticket_number']} was successfully confirmed. Receipt #{$payment['receipt_number']} has been generated.";
        $nStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, ticket_id, title, message, channel, status, created_at)
            VALUES (?, ?, 'Payment Receipt Confirmed', ?, 'in_app', 'sent', NOW())
        ");
        $nStmt->execute([$payment['user_id'], $payment['ticket_id'], $notifMsg]);

        // Dispatch SMS receipt notification
        if (!empty($payment['payer_phone'])) {
            $smsReceipt = "CampusFix: Payment of {$currency} " . number_format($payment['amount'], 2) . " received for Ticket {$payment['ticket_number']}. Receipt: {$payment['receipt_number']}.";
            SmsService::sendSms($payment['payer_phone'], $smsReceipt, $payment['ticket_id'], $payment['user_id']);
        }

        // Log system activity
        log_activity($payment['user_id'], 'payment_completed', 'payment', $payment['id'], [
            'ticket_number'  => $payment['ticket_number'],
            'reference'      => $reference,
            'receipt_number' => $payment['receipt_number'],
            'amount'         => $payment['amount']
        ]);

        return [
            'success'        => true,
            'status'         => 'success',
            'reference'      => $reference,
            'receipt_number' => $payment['receipt_number'],
            'ticket_id'      => $payment['ticket_id'],
            'ticket_number'  => $payment['ticket_number'],
            'amount'         => $payment['amount'],
            'message'        => 'Payment successfully verified and recorded.'
        ];
    }

    /**
     * Get payment details by ticket ID
     */
    public static function getPaymentByTicket(int $ticketId): ?array
    {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            SELECT p.*, u.full_name as payer_name, u.email as payer_email, t.ticket_number, t.title as ticket_title 
            FROM payments p
            JOIN users u ON p.user_id = u.id
            JOIN tickets t ON p.ticket_id = t.id
            WHERE p.ticket_id = ?
            ORDER BY p.id DESC 
            LIMIT 1
        ");
        $stmt->execute([$ticketId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get payment details by receipt number
     */
    public static function getPaymentByReceipt(string $receiptNumber): ?array
    {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            SELECT p.*, u.full_name as payer_name, u.email as payer_email, u.user_code as student_id,
                   t.ticket_number, t.title as ticket_title, t.billing_description,
                   c.name as category_name, l.building_name, l.room_name
            FROM payments p
            JOIN users u ON p.user_id = u.id
            JOIN tickets t ON p.ticket_id = t.id
            LEFT JOIN categories c ON t.category_id = c.id
            LEFT JOIN campus_locations l ON t.location_id = l.id
            WHERE p.receipt_number = ?
            LIMIT 1
        ");
        $stmt->execute([$receiptNumber]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get recent payments list for Admin Desk
     */
    public static function getRecentPayments(int $limit = 30): array
    {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            SELECT p.*, u.full_name as payer_name, t.ticket_number, t.title as ticket_title 
            FROM payments p
            JOIN users u ON p.user_id = u.id
            JOIN tickets t ON p.ticket_id = t.id
            ORDER BY p.id DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
