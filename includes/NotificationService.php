<?php
/**
 * CampusFix - Multi-Channel Notification Dispatcher
 * Coordinates In-App, Email (PHPMailerLite), and SMS dispatch across all ticket lifecycle triggers.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/PHPMailerLite.php';
require_once __DIR__ . '/SmsService.php';

class NotificationService
{
    /**
     * Dispatch notification when a new ticket is submitted
     *
     * @param int $ticketId
     * @return void
     */
    public static function notifyTicketCreated(int $ticketId): void
    {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("
                SELECT t.*, r.full_name as reporter_name, r.email as reporter_email, c.name as category_name, l.building_name
                FROM tickets t
                JOIN users r ON t.reporter_id = r.id
                JOIN categories c ON t.category_id = c.id
                JOIN campus_locations l ON t.location_id = l.id
                WHERE t.id = ?
            ");
            $stmt->execute([$ticketId]);
            $ticket = $stmt->fetch();

            if (!$ticket) return;

            // 1. Email to Reporter (Confirmation of receipt)
            $subject = "Fault Report Received: [{$ticket['ticket_number']}]";
            $body = self::renderEmailTemplate(
                "Incident Report Received",
                $ticket['reporter_name'],
                "Thank you for reporting a campus maintenance issue. Your report has been registered in the system with tracking number <strong>{$ticket['ticket_number']}</strong>.",
                [
                    'Ticket ID' => $ticket['ticket_number'],
                    'Category'  => $ticket['category_name'],
                    'Location'  => $ticket['building_name'],
                    'Status'    => 'Pending Triage',
                    'Date'      => date('M j, Y g:i A')
                ],
                BASE_URL . "student/ticket-details.php?id=" . $ticket['id'],
                "Track Incident Status"
            );

            $mailer = new PHPMailerLite();
            $mailer->send($ticket['reporter_email'], $subject, $body);

            // 2. Alert all active Administrators in-app
            $adminIds = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, ticket_id, title, message, channel, status, created_at)
                VALUES (?, ?, ?, ?, 'in_app', 'sent', NOW())
            ");
            foreach ($adminIds as $adminId) {
                $notifStmt->execute([
                    $adminId,
                    $ticketId,
                    "New Fault ({$ticket['ticket_number']})",
                    "Student {$ticket['reporter_name']} reported {$ticket['category_name']} in {$ticket['building_name']}."
                ]);
            }

        } catch (Exception $e) {
            error_log("notifyTicketCreated Exception: " . $e->getMessage());
        }
    }

    /**
     * Dispatch notification when ticket is assigned to a technician
     *
     * @param int $ticketId
     * @param int $technicianId
     * @return void
     */
    public static function notifyTicketAssigned(int $ticketId, int $technicianId): void
    {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("
                SELECT t.*, r.full_name as reporter_name, r.email as reporter_email, r.phone as reporter_phone,
                       tech.full_name as tech_name, tech.email as tech_email, tech.phone as tech_phone,
                       c.name as category_name, l.building_name, l.room_number
                FROM tickets t
                JOIN users r ON t.reporter_id = r.id
                JOIN users tech ON tech.id = ?
                JOIN categories c ON t.category_id = c.id
                JOIN campus_locations l ON t.location_id = l.id
                WHERE t.id = ?
            ");
            $stmt->execute([$technicianId, $ticketId]);
            $ticket = $stmt->fetch();

            if (!$ticket) return;

            // 1. Email to Technician
            $techSubject = "New Work Order Dispatched: [{$ticket['ticket_number']}]";
            $techBody = self::renderEmailTemplate(
                "New Maintenance Job Assigned",
                $ticket['tech_name'],
                "An administrator has dispatched an incident report to you for diagnosis and repair.",
                [
                    'Ticket ID' => $ticket['ticket_number'],
                    'Problem'   => $ticket['title'],
                    'Location'  => "{$ticket['building_name']} ({$ticket['room_number']})",
                    'Priority'  => strtoupper($ticket['priority']),
                    'Reporter'  => "{$ticket['reporter_name']} ({$ticket['reporter_phone']})"
                ],
                BASE_URL . "technician/ticket-details.php?id=" . $ticket['id'],
                "Inspect Work Order"
            );
            $mailer = new PHPMailerLite();
            $mailer->send($ticket['tech_email'], $techSubject, $techBody);

            // 2. Optional SMS Alert to Technician if priority is Urgent / High
            if (in_array($ticket['priority'], ['urgent', 'high']) && !empty($ticket['tech_phone'])) {
                $smsText = "CampusFix URGENT JOB: {$ticket['ticket_number']} in {$ticket['building_name']} assigned to you. Details: {$ticket['title']}";
                SmsService::sendSms($ticket['tech_phone'], $smsText, $ticketId, $technicianId);
            }

            // 3. Email to Student Reporter
            $userSubject = "Technician Assigned: [{$ticket['ticket_number']}]";
            $userBody = self::renderEmailTemplate(
                "Technician Assigned to Your Fault",
                $ticket['reporter_name'],
                "Great news! Your ticket has been reviewed by administrators and assigned to technician <strong>{$ticket['tech_name']}</strong>.",
                [
                    'Ticket ID'  => $ticket['ticket_number'],
                    'Technician' => $ticket['tech_name'],
                    'Status'     => 'Assigned',
                    'Priority'   => ucfirst($ticket['priority'])
                ],
                BASE_URL . "student/ticket-details.php?id=" . $ticket['id'],
                "Track Status"
            );
            $mailer->send($ticket['reporter_email'], $userSubject, $userBody);

        } catch (Exception $e) {
            error_log("notifyTicketAssigned Exception: " . $e->getMessage());
        }
    }

    /**
     * Dispatch notification when ticket status changes (e.g. In Progress, Resolved)
     *
     * @param int $ticketId
     * @param string $newStatus
     * @return void
     */
    public static function notifyStatusChanged(int $ticketId, string $newStatus): void
    {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("
                SELECT t.*, r.full_name as reporter_name, r.email as reporter_email, r.phone as reporter_phone,
                       tech.full_name as tech_name
                FROM tickets t
                JOIN users r ON t.reporter_id = r.id
                LEFT JOIN users tech ON t.assigned_to = tech.id
                WHERE t.id = ?
            ");
            $stmt->execute([$ticketId]);
            $ticket = $stmt->fetch();

            if (!$ticket) return;

            $statusLabels = [
                'in_progress' => 'Work Started (In Progress)',
                'resolved'    => 'Repair Completed (Resolved)',
                'closed'      => 'Ticket Closed & Verified',
                'reopened'    => 'Ticket Reopened'
            ];

            $label = $statusLabels[$newStatus] ?? ucfirst($newStatus);

            // In-app notification
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, ticket_id, title, message, channel, status, created_at)
                VALUES (?, ?, ?, ?, 'in_app', 'sent', NOW())
            ");
            $notifStmt->execute([
                $ticket['reporter_id'],
                $ticketId,
                "Status: {$label} ({$ticket['ticket_number']})",
                "Your incident status changed to {$label}."
            ]);

            // Email alert
            $subject = "Ticket Status Update: [{$ticket['ticket_number']}] is now {$label}";
            $body = self::renderEmailTemplate(
                "Maintenance Status Update",
                $ticket['reporter_name'],
                "The status of your campus fault report has been updated to <strong>{$label}</strong>.",
                [
                    'Ticket ID'   => $ticket['ticket_number'],
                    'New Status'  => $label,
                    'Technician'  => $ticket['tech_name'] ?? 'Assigned Staff',
                    'Timestamp'   => date('M j, Y g:i A')
                ],
                BASE_URL . "student/ticket-details.php?id=" . $ticket['id'],
                "View Updated Ticket"
            );

            $mailer = new PHPMailerLite();
            $mailer->send($ticket['reporter_email'], $subject, $body);

            // SMS alert on resolution
            if ($newStatus === 'resolved' && !empty($ticket['reporter_phone'])) {
                $smsText = "CampusFix: Your ticket {$ticket['ticket_number']} has been marked Resolved by the technician. Please log in to confirm and rate the service.";
                SmsService::sendSms($ticket['reporter_phone'], $smsText, $ticketId, $ticket['reporter_id']);
            }

        } catch (Exception $e) {
            error_log("notifyStatusChanged Exception: " . $e->getMessage());
        }
    }

    /**
     * Clean HTML Email Template Renderer
     *
     * @param string $title
     * @param string $recipientName
     * @param string $introText
     * @param array $detailsKeyValues
     * @param string $ctaLink
     * @param string $ctaText
     * @return string
     */
    private static function renderEmailTemplate(
        string $title,
        string $recipientName,
        string $introText,
        array $detailsKeyValues,
        string $ctaLink,
        string $ctaText
    ): string {
        $rowsHtml = '';
        foreach ($detailsKeyValues as $k => $v) {
            $rowsHtml .= "<tr><td style='padding: 6px 12px; color: #64748b; font-size: 13px; font-weight: 600; width: 35%; border-bottom: 1px solid #f1f5f9;'>{$k}</td><td style='padding: 6px 12px; color: #1e293b; font-size: 13px; border-bottom: 1px solid #f1f5f9;'>{$v}</td></tr>";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f8fafc; margin: 0; padding: 20px; }
                .email-container { max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.03); }
                .email-header { background: #0f172a; padding: 24px; text-align: center; color: #ffffff; }
                .email-body { padding: 24px; color: #1e293b; font-size: 15px; line-height: 1.6; }
                .details-table { width: 100%; border-collapse: collapse; margin: 18px 0; background: #f8fafc; border-radius: 6px; }
                .btn-cta { display: inline-block; background: #2563eb; color: #ffffff !important; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; margin-top: 15px; }
                .email-footer { background: #f1f5f9; padding: 16px; text-align: center; color: #94a3b8; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='email-header'>
                    <h2 style='margin: 0; font-size: 20px; font-weight: 700;'>CampusFix Helpdesk</h2>
                    <p style='margin: 4px 0 0 0; font-size: 13px; color: #94a3b8;'>Campus Maintenance & IT Fault Management</p>
                </div>
                <div class='email-body'>
                    <h3 style='margin: 0 0 12px 0; color: #0f172a; font-size: 18px;'>{$title}</h3>
                    <p style='margin: 0 0 12px 0;'>Hello <strong>{$recipientName}</strong>,</p>
                    <p style='margin: 0 0 16px 0;'>{$introText}</p>
                    
                    <table class='details-table'>
                        {$rowsHtml}
                    </table>

                    <div style='text-align: center; margin: 24px 0 10px 0;'>
                        <a href='{$ctaLink}' class='btn-cta'>{$ctaText}</a>
                    </div>
                </div>
                <div class='email-footer'>
                    This is an automated university notification from CampusFix. Please do not reply directly to this email.
                </div>
            </div>
        </body>
        </html>
        ";
    }
}

