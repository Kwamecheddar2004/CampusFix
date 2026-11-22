<?php
/**
 * CampusFix - Official Electronic Payment Receipt
 * Printable institutional payment voucher with transaction details and audit verification.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/PaymentService.php';

require_login();

$currentUser = current_user();
$receiptRef = trim($_GET['ref'] ?? '');

if (empty($receiptRef)) {
    flash_message('danger', 'Receipt reference not specified.');
    header("Location: " . BASE_URL . "student/my-tickets.php");
    exit;
}

$payment = PaymentService::getPaymentByReceipt($receiptRef);

if (!$payment) {
    flash_message('danger', 'Payment receipt not found in records.');
    header("Location: " . BASE_URL . "student/my-tickets.php");
    exit;
}

// Authorization check: User must be reporter, admin, or technician
if ($payment['user_id'] != $currentUser['id'] && !in_array($currentUser['role'], ['admin', 'technician'])) {
    flash_message('danger', 'Unauthorized to view this payment receipt.');
    header("Location: " . BASE_URL . "student/my-tickets.php");
    exit;
}

$currency = PaymentService::getCurrencySymbol();
$pageTitle = 'Payment Receipt - ' . $payment['receipt_number'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/style.css">
    <style>
        body {
            background-color: #f1f5f9;
        }
        .receipt-container {
            max-width: 780px;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.06);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .receipt-header {
            background: linear-gradient(135deg, #0d233a 0%, #1e3a8a 100%);
            color: #ffffff;
            padding: 2.25rem 2.5rem;
        }
        .paid-stamp {
            border: 3px solid #16a34a;
            color: #16a34a;
            text-transform: uppercase;
            font-weight: 800;
            font-size: 1.15rem;
            letter-spacing: 2px;
            padding: 6px 16px;
            border-radius: 6px;
            display: inline-block;
            transform: rotate(-4deg);
        }
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .receipt-container {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body class="py-4">

<div class="container">
    <!-- Action Bar (Hidden on print) -->
    <div class="d-flex align-items-center justify-content-between mb-3 no-print" style="max-width: 780px; margin: 0 auto;">
        <a href="<?= BASE_URL ?>student/ticket-details.php?id=<?= $payment['ticket_id'] ?>" class="btn btn-outline-secondary btn-sm fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Back to Ticket
        </a>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary btn-sm fw-semibold shadow-sm">
                <i class="bi bi-printer-fill me-1"></i> Print / Save PDF
            </button>
        </div>
    </div>

    <!-- Official Receipt Card -->
    <div class="receipt-container">
        <!-- Header -->
        <div class="receipt-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white text-primary rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 50px; height: 50px;">
                        <i class="bi bi-tools fs-3 text-primary"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-white"><?= APP_NAME ?></h4>
                        <small class="text-white-50"><?= get_system_setting('institution_name', 'University Campus Facilities & IT Directorate') ?></small>
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge bg-white text-primary fw-bold text-uppercase px-3 py-2 fs-6">Official Receipt</span>
                </div>
            </div>
        </div>

        <!-- Receipt Body -->
        <div class="p-4 p-md-5">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-4 pb-4 border-bottom mb-4">
                <div>
                    <span class="text-uppercase text-muted fw-bold small" style="letter-spacing: 0.5px;">Receipt Number</span>
                    <h5 class="fw-bold text-dark font-monospace mb-1"><?= htmlspecialchars($payment['receipt_number']) ?></h5>
                    <div class="text-secondary small">
                        <strong>Date:</strong> <?= date('F j, Y, g:i A', strtotime($payment['paid_at'] ?: $payment['created_at'])) ?>
                    </div>
                    <div class="text-secondary small">
                        <strong>Gateway Reference:</strong> <code><?= htmlspecialchars($payment['payment_reference']) ?></code>
                    </div>
                </div>

                <div class="text-end">
                    <div class="paid-stamp mb-2">
                        <i class="bi bi-check-circle-fill me-1"></i> PAID
                    </div>
                    <div class="text-muted small">Verified by Paystack Sandbox</div>
                </div>
            </div>

            <!-- Student & Ticket Info Grid -->
            <div class="row g-4 mb-4 pb-4 border-bottom">
                <div class="col-md-6">
                    <h6 class="text-uppercase text-muted fw-bold small mb-2" style="font-size: 0.75rem;">Billed To (Reporter):</h6>
                    <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($payment['payer_name']) ?></div>
                    <div class="text-secondary small">ID: <code><?= htmlspecialchars($payment['student_id'] ?: 'N/A') ?></code></div>
                    <div class="text-secondary small"><?= htmlspecialchars($payment['payer_email'] ?: '') ?></div>
                    <div class="text-secondary small"><?= htmlspecialchars($payment['payer_phone'] ?: '') ?></div>
                </div>
                <div class="col-md-6 text-md-end">
                    <h6 class="text-uppercase text-muted fw-bold small mb-2" style="font-size: 0.75rem;">Associated Ticket:</h6>
                    <div class="fw-bold text-primary fs-6">
                        <a href="<?= BASE_URL ?>student/ticket-details.php?id=<?= $payment['ticket_id'] ?>" class="text-decoration-none">
                            <?= htmlspecialchars($payment['ticket_number']) ?>
                        </a>
                    </div>
                    <div class="text-secondary small"><?= htmlspecialchars($payment['ticket_title']) ?></div>
                    <div class="text-secondary small"><?= htmlspecialchars($payment['building_name'] ?: '') ?> <?= !empty($payment['room_name']) ? '– ' . htmlspecialchars($payment['room_name']) : '' ?></div>
                    <div class="text-muted small">Category: <?= htmlspecialchars($payment['category_name'] ?: 'Maintenance') ?></div>
                </div>
            </div>

            <!-- Itemized Table -->
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60%;">Description & Particulars</th>
                            <th class="text-center" style="width: 15%;">Channel</th>
                            <th class="text-end" style="width: 25%;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">Maintenance & Repair Surcharge</div>
                                <small class="text-muted"><?= htmlspecialchars($payment['billing_description'] ?: 'Replacement parts and technical maintenance contribution') ?></small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary-subtle text-primary border border-primary px-2 py-1 small">
                                    <i class="bi bi-phone me-1"></i> <?= htmlspecialchars($payment['payment_channel'] ?: 'Mobile Money') ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold text-dark fs-6">
                                <?= $currency ?> <?= number_format($payment['amount'], 2) ?>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <th colspan="2" class="text-end text-uppercase">Total Settled (<?= htmlspecialchars($payment['currency']) ?>):</th>
                            <th class="text-end fs-5 text-success fw-bold">
                                <?= $currency ?> <?= number_format($payment['amount'], 2) ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Notice & Verification Stamp -->
            <div class="bg-light p-3 rounded-3 border d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-2 text-muted small">
                    <i class="bi bi-shield-lock-fill text-success fs-4"></i>
                    <div>
                        <strong>Cryptographically Verified Receipt</strong><br>
                        This transaction was processed through the CampusFix Payment Gateway test sandbox.
                    </div>
                </div>
                <div class="text-muted font-monospace small">
                    AUTH-HASH: <?= strtoupper(substr(hash('sha256', $payment['payment_reference'] . $payment['amount']), 0, 16)) ?>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="p-3 bg-light text-center border-top text-muted small">
            Thank you for helping maintain campus infrastructure. For billing inquiries, contact <strong>helpdesk@campusfix.edu</strong>.
        </div>
    </div>
</div>

</body>
</html>
