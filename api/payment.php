<?php
/**
 * CampusFix - Payment Gateway AJAX API
 * Handles transaction initialization, sandbox simulation, and Paystack verification callbacks.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/PaymentService.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$currentUser = current_user();
$pdo = get_db_connection();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? '';

try {
    // -------------------------------------------------------------------------
    // Action 1: Initialize Payment Checkout
    // -------------------------------------------------------------------------
    if ($action === 'init_checkout') {
        $ticketId = (int) ($input['ticket_id'] ?? 0);

        if ($ticketId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid ticket ID provided.']);
            exit;
        }

        // Fetch ticket
        $stmt = $pdo->prepare("
            SELECT t.*, u.email as reporter_email, u.phone as reporter_phone 
            FROM tickets t
            JOIN users u ON t.reporter_id = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
            exit;
        }

        // Authorization: Only the reporter or admin can pay
        if ($ticket['reporter_id'] != $currentUser['id'] && $currentUser['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized: You can only pay for your own tickets.']);
            exit;
        }

        if ($ticket['payment_status'] === 'paid') {
            echo json_encode(['success' => false, 'message' => 'This surcharge has already been settled and confirmed.']);
            exit;
        }

        if ($ticket['billing_amount'] <= 0) {
            echo json_encode(['success' => false, 'message' => 'This ticket has no outstanding billing surcharge.']);
            exit;
        }

        $initData = PaymentService::initializePayment(
            $ticketId,
            $currentUser['id'],
            (float) $ticket['billing_amount'],
            $currentUser['email'] ?: $ticket['reporter_email'],
            $currentUser['phone'] ?: ($ticket['reporter_phone'] ?: '+233200000000')
        );

        $initData['ticket_number'] = $ticket['ticket_number'];
        $initData['description'] = $ticket['billing_description'] ?: 'Maintenance Repair & Materials';
        $initData['currency_symbol'] = PaymentService::getCurrencySymbol();
        $initData['amount_formatted'] = number_format($ticket['billing_amount'], 2);

        echo json_encode(['success' => true, 'data' => $initData]);
        exit;
    }

    // -------------------------------------------------------------------------
    // Action 2: Verify & Confirm Payment (Sandbox Simulation or Live Gateway)
    // -------------------------------------------------------------------------
    if ($action === 'verify_payment') {
        $reference = trim($input['reference'] ?? '');
        $channel = trim($input['channel'] ?? 'MTN MoMo');

        if (empty($reference)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Payment reference is required for verification.']);
            exit;
        }

        $result = PaymentService::verifyPayment($reference, [
            'channel' => $channel
        ]);

        if ($result['success']) {
            $result['receipt_url'] = BASE_URL . 'student/receipt.php?ref=' . urlencode($result['receipt_number']);
            echo json_encode($result);
        } else {
            http_response_code(422);
            echo json_encode($result);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => "Unrecognized payment action '{$action}'."]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage()]);
}
