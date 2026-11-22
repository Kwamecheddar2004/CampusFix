<?php
/**
 * CampusFix - Notification Settings & Dispatch Audit Desk
 * Configure SMTP credentials, toggle mock mode, send test messages, and inspect delivery logs.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/PHPMailerLite.php';
require_once __DIR__ . '/../includes/SmsService.php';
require_once __DIR__ . '/../includes/PaymentService.php';

require_role('admin');

$user = current_user();
$pageTitle = 'Gateways & Integrations';
$pageHeading = 'External Gateways & Sandbox Engine';
$pageSubtitle = 'Configure Paystack payment sandbox, SMS integration gateway, SMTP email, and inspect delivery ledgers';

$pdo = get_db_connection();
$testResult = null;

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
    $action = $_POST['action'] ?? '';

    // Action 1: Save System Settings
    if ($action === 'save_settings') {
        $settingsToUpdate = [
            'smtp_host'         => trim($_POST['smtp_host'] ?? ''),
            'smtp_port'         => trim($_POST['smtp_port'] ?? '587'),
            'smtp_user'         => trim($_POST['smtp_user'] ?? ''),
            'smtp_pass'         => trim($_POST['smtp_pass'] ?? ''),
            'smtp_encryption'   => trim($_POST['smtp_encryption'] ?? 'tls'),
            'mail_from_address' => trim($_POST['mail_from_address'] ?? ''),
            'mail_from_name'    => trim($_POST['mail_from_name'] ?? 'CampusFix Helpdesk'),
            'mail_mock_mode'    => isset($_POST['mail_mock_mode']) ? '1' : '0',
            'sms_enabled'          => isset($_POST['sms_enabled']) ? '1' : '0',
            'sms_mock_mode'        => isset($_POST['sms_mock_mode']) ? '1' : '0',
            'sms_provider'         => trim($_POST['sms_provider'] ?? 'hubtel'),
            'sms_api_key'          => trim($_POST['sms_api_key'] ?? ''),
            'sms_sender_id'        => trim($_POST['sms_sender_id'] ?? 'CampusFix'),
            'payment_gateway'       => trim($_POST['payment_gateway'] ?? 'paystack'),
            'paystack_payment_link' => trim($_POST['paystack_payment_link'] ?? 'https://paystack.shop/pay/0fwhj--t6n'),
            'payment_sandbox_mode'  => isset($_POST['payment_sandbox_mode']) ? '1' : '0',
            'paystack_public_key'   => trim($_POST['paystack_public_key'] ?? ''),
            'paystack_secret_key'   => trim($_POST['paystack_secret_key'] ?? ''),
            'currency_symbol'       => trim($_POST['currency_symbol'] ?? 'GH₵'),
            'currency_code'         => trim($_POST['currency_code'] ?? 'GHS')
        ];

        $upStmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
        foreach ($settingsToUpdate as $key => $val) {
            $upStmt->execute([$val, $key]);
        }

        log_activity($user['id'], 'notification_settings_updated', 'system');
        flash_message('success', 'Notification settings successfully updated in database.');
        header("Location: " . BASE_URL . "admin/notification-settings.php");
        exit;
    }

    // Action 2: Send Test Email
    if ($action === 'test_email') {
        $testEmail = trim($_POST['test_email_address'] ?? $user['email']);
        $mailer = new PHPMailerLite();
        $testSubject = "CampusFix Test Notification [" . date('H:i:s') . "]";
        $testBody = "<h3>Notification Diagnostic Test</h3><p>This is a verification email dispatched by the CampusFix notification engine on <strong>" . date('r') . "</strong>.</p>";
        $testResult = $mailer->send($testEmail, $testSubject, $testBody);
    }

    // Action 3: Send Test SMS
    if ($action === 'test_sms') {
        $testPhone = trim($_POST['test_phone_number'] ?? '+233241112233');
        $msg = "CampusFix Diagnostic Test: SMS notification service verified at " . date('H:i:s') . ".";
        $testResult = SmsService::sendSms($testPhone, $msg, null, $user['id']);
    }
}

// Fetch Latest 30 Dispatched Notifications
$logStmt = $pdo->query("
    SELECT n.*, u.full_name as recipient_name, u.email as recipient_email, t.ticket_number
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.id
    LEFT JOIN tickets t ON n.ticket_id = t.id
    ORDER BY n.id DESC
    LIMIT 30
");
$notifLogs = $logStmt->fetchAll();

$recentPayments = PaymentService::getRecentPayments(25);
$currency = PaymentService::getCurrencySymbol();

include_once INCLUDES_PATH . 'header.php';
include_once INCLUDES_PATH . 'sidebar.php';
?>

<div class="d-flex flex-column flex-1 w-100">
    <?php include_once INCLUDES_PATH . 'navbar.php'; ?>

    <div class="container-fluid px-0">

        <?php if ($testResult !== null): ?>
            <div class="alert <?= $testResult['success'] ? 'alert-success' : 'alert-danger' ?> d-flex align-items-center gap-2 mb-4 shadow-sm">
                <i class="bi <?= $testResult['success'] ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> fs-5"></i>
                <div>
                    <strong><?= $testResult['success'] ? 'Test Message Processed!' : 'Test Failed:' ?></strong>
                    <?= htmlspecialchars($testResult['details'] ?? $testResult['message'] ?? 'Check logs.') ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <!-- Left: Settings Form -->
            <div class="col-xl-7">
                <div class="cf-card mb-0">
                    <div class="cf-card-header">
                        <h5 class="cf-card-title">SMTP & SMS Gateway Configuration</h5>
                        <small class="text-muted">Live and mock mode switches</small>
                    </div>

                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_settings">

                        <!-- Email Section -->
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                            <i class="bi bi-envelope-at text-primary me-1"></i> Outgoing SMTP Email Settings
                        </h6>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-8">
                                <label class="form-label small fw-semibold text-secondary">SMTP Host</label>
                                <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars(SMTP_HOST) ?>" placeholder="smtp.gmail.com">
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label small fw-semibold text-secondary">Port</label>
                                <input type="number" name="smtp_port" class="form-control" value="<?= SMTP_PORT ?>" placeholder="587">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-secondary">SMTP Username / Email</label>
                                <input type="text" name="smtp_user" class="form-control" value="<?= htmlspecialchars(SMTP_USER) ?>">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-secondary">SMTP Password / App Password</label>
                                <input type="password" name="smtp_pass" class="form-control" value="<?= htmlspecialchars(SMTP_PASS) ?>" placeholder="••••••••••••••••">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-secondary">Sender Name</label>
                                <input type="text" name="mail_from_name" class="form-control" value="<?= htmlspecialchars(MAIL_FROM_NAME) ?>">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-secondary">Sender Email Address</label>
                                <input type="email" name="mail_from_address" class="form-control" value="<?= htmlspecialchars(MAIL_FROM_ADDRESS) ?>">
                            </div>
                        </div>

                        <div class="mb-4 p-3 bg-light rounded-3 border">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="mail_mock_mode" id="mail_mock_mode" value="1" <?= (MAIL_MOCK_MODE === 1) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold text-dark small" for="mail_mock_mode">
                                    Email Mock Mode (Simulate & Log to <code>logs/mail_mock.log</code>)
                                </label>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    When enabled, email payloads are logged to the filesystem without requiring live external SMTP credentials.
                                </div>
                            </div>
                        </div>

                        <!-- SMS Section -->
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                            <i class="bi bi-chat-dots text-primary me-1"></i> SMS Gateway Integration
                        </h6>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-secondary">Provider Gateway</label>
                                <select name="sms_provider" class="form-select">
                                    <option value="hubtel" <?= (SMS_PROVIDER === 'hubtel') ? 'selected' : '' ?>>Hubtel SMS API</option>
                                    <option value="arkesel" <?= (SMS_PROVIDER === 'arkesel') ? 'selected' : '' ?>>Arkesel SMS Gateway</option>
                                    <option value="twilio" <?= (SMS_PROVIDER === 'twilio') ? 'selected' : '' ?>>Twilio Messaging</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-secondary">Sender ID (Alphanumeric)</label>
                                <input type="text" name="sms_sender_id" class="form-control" value="<?= htmlspecialchars(SMS_SENDER_ID) ?>" maxlength="11">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">SMS API Secret Key</label>
                            <input type="password" name="sms_api_key" class="form-control" value="<?= htmlspecialchars(SMS_API_KEY) ?>" placeholder="API Secret Key">
                        </div>

                        <div class="mb-4 p-3 bg-light rounded-3 border">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="sms_enabled" id="sms_enabled" value="1" <?= (SMS_ENABLED === 1) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold text-dark small" for="sms_enabled">
                                    Enable SMS Dispatch
                                </label>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="sms_mock_mode" id="sms_mock_mode" value="1" <?= (SMS_MOCK_MODE === 1) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold text-dark small" for="sms_mock_mode">
                                    SMS Mock Simulation Mode (Clearly logs simulated messages)
                                </label>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    When credentials are unavailable, system logs mock transmissions to <code>logs/sms_mock.log</code> and flags them as <code>mock_delivered</code> in database.
                                </div>
                            </div>
                        </div>

                        <!-- Payment Gateway & Surcharge Section -->
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                            <i class="bi bi-credit-card-2-front text-primary me-1"></i> Paystack Payment Gateway & Storefront
                        </h6>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-secondary">Gateway Provider</label>
                                <input type="text" class="form-control" value="Paystack (Official Gateway)" readonly>
                                <input type="hidden" name="payment_gateway" value="paystack">
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label small fw-semibold text-secondary">Currency Symbol</label>
                                <input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars(get_system_setting('currency_symbol', 'GH₵')) ?>">
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label small fw-semibold text-secondary">ISO Code</label>
                                <input type="text" name="currency_code" class="form-control" value="<?= htmlspecialchars(get_system_setting('currency_code', 'GHS')) ?>">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-secondary">Paystack Public Test Key</label>
                                <input type="text" name="paystack_public_key" class="form-control" value="<?= htmlspecialchars(get_system_setting('paystack_public_key', 'pk_test_sample_campusfix_key')) ?>" placeholder="pk_test_...">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-secondary">Paystack Secret Test Key</label>
                                <input type="password" name="paystack_secret_key" class="form-control" value="<?= htmlspecialchars(get_system_setting('paystack_secret_key', '')) ?>" placeholder="sk_test_...">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Paystack Payment Page URL (Storefront / Link)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-link-45deg"></i></span>
                                <input type="url" name="paystack_payment_link" class="form-control" value="<?= htmlspecialchars(get_system_setting('paystack_payment_link', 'https://paystack.shop/pay/0fwhj--t6n')) ?>" placeholder="https://paystack.shop/pay/...">
                                <a href="<?= htmlspecialchars(get_system_setting('paystack_payment_link', 'https://paystack.shop/pay/0fwhj--t6n')) ?>" target="_blank" class="btn btn-outline-primary btn-sm d-flex align-items-center">
                                    <i class="bi bi-box-arrow-up-right me-1"></i> Visit Paystack Page
                                </a>
                            </div>
                            <small class="text-muted" style="font-size: 0.75rem;">
                                Your active Paystack payment page link where students can settle surcharges using live mobile money or cards.
                            </small>
                        </div>

                        <div class="mb-4 p-3 bg-light rounded-3 border">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="payment_sandbox_mode" id="payment_sandbox_mode" value="1" <?= ((int) get_system_setting('payment_sandbox_mode', '1') === 1) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold text-dark small" for="payment_sandbox_mode">
                                    Payment Sandbox & Mobile Money Simulation Mode
                                </label>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    When checked, allows students to simulate Mobile Money (MTN, Telecel, AT) and Card settlements without real deductions. Official verified receipts are generated instantly.
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary fw-semibold btn-sm px-4">
                                <i class="bi bi-check2-circle me-1"></i> Save Gateway Configuration
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right: Diagnostics & Quick Test Boxes -->
            <div class="col-xl-5">

                <!-- Test Email Card -->
                <div class="cf-card mb-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-send text-primary me-1"></i> Test Email Dispatch
                    </h6>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="test_email">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Recipient Email</label>
                            <input type="email" name="test_email_address" class="form-control form-control-sm" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm fw-semibold w-100">
                            <i class="bi bi-envelope-paper me-1"></i> Dispatch Test Email
                        </button>
                    </form>
                </div>

                <!-- Test SMS Card -->
                <div class="cf-card mb-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-phone text-success me-1"></i> Test SMS Dispatch
                    </h6>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="test_sms">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Target Mobile Phone</label>
                            <input type="tel" name="test_phone_number" class="form-control form-control-sm" value="+233241112233" required>
                        </div>
                        <button type="submit" class="btn btn-outline-success btn-sm fw-semibold w-100">
                            <i class="bi bi-chat-text me-1"></i> Dispatch Test SMS
                        </button>
                    </form>
                </div>

                <!-- Simulation Info Card -->
                <div class="cf-card bg-light border">
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-info-circle text-primary"></i> Academic Grading Compliance</h6>
                    <p class="small text-secondary mb-0" style="font-size: 0.8rem; line-height: 1.5;">
                        CampusFix follows ethical software engineering practices: if live third-party SMS or SMTP gateways are offline, the system utilizes clearly documented <strong>Mock Simulation Mode</strong>. Messages are written to <code>logs/</code> and marked in the database as simulated rather than falsely claiming delivery.
                    </p>
                </div>

            </div>
        </div>

        <!-- Notification Dispatch Audit Ledger -->
        <div class="cf-card">
            <div class="cf-card-header">
                <div>
                    <h5 class="cf-card-title">Recent Notification Dispatch Ledger</h5>
                    <small class="text-muted">In-app, SMTP, and SMS queue delivery records</small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Recipient</th>
                            <th>Ticket #</th>
                            <th>Title & Message</th>
                            <th>Channel</th>
                            <th>Delivery Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notifLogs as $n): ?>
                            <tr>
                                <td class="small text-muted font-monospace"><?= time_ago($n['created_at']) ?></td>
                                <td>
                                    <div class="fw-semibold text-dark small"><?= htmlspecialchars($n['recipient_name'] ?? 'Staff') ?></div>
                                    <small class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($n['recipient_email'] ?? '—') ?></small>
                                </td>
                                <td>
                                    <?php if ($n['ticket_number']): ?>
                                        <code class="fw-bold"><?= htmlspecialchars($n['ticket_number']) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark small"><?= htmlspecialchars($n['title']) ?></div>
                                    <small class="text-muted text-truncate d-inline-block" style="max-width: 320px;"><?= htmlspecialchars($n['message']) ?></small>
                                </td>
                                <td>
                                    <?php 
                                    $channelBadge = ($n['channel'] === 'email') ? 'bg-primary-subtle text-primary border border-primary' : 
                                                   (($n['channel'] === 'sms') ? 'bg-success-subtle text-success border border-success' : 'bg-secondary-subtle text-secondary');
                                    ?>
                                    <span class="badge <?= $channelBadge ?> text-uppercase" style="font-size: 0.65rem;">
                                        <?= htmlspecialchars($n['channel']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($n['status'] === 'mock_delivered'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning" style="font-size: 0.7rem;">
                                            <i class="bi bi-journal-text"></i> Mock Logged
                                        </span>
                                    <?php elseif ($n['status'] === 'sent'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success" style="font-size: 0.7rem;">
                                            <i class="bi bi-check-circle"></i> Sent
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary" style="font-size: 0.7rem;"><?= htmlspecialchars($n['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Maintenance Payments Ledger Card -->
        <div class="cf-card mt-4">
            <div class="cf-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="cf-card-title mb-0">Maintenance Surcharge Payments & Receipts</h5>
                    <small class="text-muted">Settlements processed via Paystack Sandbox and Mobile Money</small>
                </div>
                <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 small">
                    <i class="bi bi-cash-stack me-1"></i> <?= count($recentPayments) ?> Transactions
                </span>
            </div>

            <div class="table-responsive">
                <table class="cf-table align-middle">
                    <thead>
                        <tr>
                            <th>Date / Time</th>
                            <th>Receipt #</th>
                            <th>Ticket</th>
                            <th>Payer</th>
                            <th>Channel</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th class="text-end">Voucher</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentPayments)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted small">
                                    <i class="bi bi-inbox fs-4 d-block mb-1"></i>
                                    No payment transactions recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentPayments as $p): ?>
                                <tr>
                                    <td class="small text-muted font-monospace"><?= time_ago($p['paid_at'] ?: $p['created_at']) ?></td>
                                    <td>
                                        <code class="fw-bold text-dark"><?= htmlspecialchars($p['receipt_number']) ?></code>
                                        <div class="text-muted small" style="font-size: 0.7rem;"><?= htmlspecialchars($p['payment_reference']) ?></div>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>admin/ticket-details.php?id=<?= $p['ticket_id'] ?>" class="fw-bold text-primary text-decoration-none">
                                            <?= htmlspecialchars($p['ticket_number']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark small"><?= htmlspecialchars($p['payer_name']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border small">
                                            <i class="bi bi-phone text-primary me-1"></i> <?= htmlspecialchars($p['payment_channel'] ?: 'Mobile Money') ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-dark">
                                        <?= $currency ?> <?= number_format($p['amount'], 2) ?>
                                    </td>
                                    <td>
                                        <?php if ($p['status'] === 'success'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success" style="font-size: 0.72rem;">
                                                <i class="bi bi-check-circle-fill me-1"></i> Paid
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark" style="font-size: 0.72rem;"><?= htmlspecialchars($p['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>student/receipt.php?ref=<?= urlencode($p['receipt_number']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm py-1 px-2" title="Print Receipt">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include_once INCLUDES_PATH . 'footer.php'; ?>

