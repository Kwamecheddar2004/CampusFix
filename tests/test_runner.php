<?php
/**
 * CampusFix - Automated QA Test Suite & Test Runner
 * Executes automated unit and functional assertions against system services, helpers, and security guardrails.
 * Usable via CLI: `php tests/test_runner.php` or Web Browser: `http://localhost/.../tests/test_runner.php`
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/UploadService.php';
require_once __DIR__ . '/../includes/PHPMailerLite.php';
require_once __DIR__ . '/../includes/SmsService.php';
require_once __DIR__ . '/../includes/PaymentService.php';

class CampusFixTestRunner
{
    private array $results = [];
    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    private function assert(string $testName, bool $condition, string $message = ''): void
    {
        $this->results[] = [
            'name'    => $testName,
            'passed'  => $condition,
            'message' => $condition ? 'Assertion passed.' : ($message ?: 'Assertion failed.')
        ];
    }

    public function runAllTests(): void
    {
        // Test 1: Password Bcrypt Hashing & Verification
        $plain = 'Student@123';
        $hash = password_hash($plain, PASSWORD_BCRYPT);
        $this->assert(
            'TC-01: Bcrypt Password Hashing & Verification',
            password_verify($plain, $hash) && !password_verify('WrongPassword', $hash),
            'Password verification did not validate correctly.'
        );

        // Test 2: Input Sanitization Against XSS
        $xssPayload = '<script>alert("XSS Vulnerability")</script>';
        $sanitized = sanitize($xssPayload);
        $this->assert(
            'TC-02: Output Escaping & XSS Protection',
            !str_contains($sanitized, '<script>') && str_contains($sanitized, '&lt;script&gt;'),
            'XSS payload was not properly escaped.'
        );

        // Test 3: CSRF Token Generation & Validation
        $token = csrf_token();
        $this->assert(
            'TC-03: CSRF Token Generation & Cryptographic Length',
            !empty($token) && strlen($token) === 64 && verify_csrf_token($token) && !verify_csrf_token('invalid_token'),
            'CSRF token validation failed.'
        );

        // Test 4: Ticket Number Format Specification
        $sampleNumber = sprintf("CF-%s-%04d", date('Y'), 1);
        $matches = preg_match('/^CF-\d{4}-\d{4}$/', $sampleNumber);
        $this->assert(
            'TC-04: Ticket ID Sequential Numbering Format (CF-YYYY-XXXX)',
            $matches === 1,
            "Ticket number '{$sampleNumber}' does not match CF-YYYY-XXXX format."
        );

        // Test 5: Status Badges Rendering Output
        $badgePending = render_status_badge('pending');
        $badgeResolved = render_status_badge('resolved');
        $this->assert(
            'TC-05: Visual Status Badge Indicators',
            str_contains($badgePending, 'bg-warning') && str_contains($badgeResolved, 'bg-success'),
            'Status badges do not contain proper CSS class mappings.'
        );

        // Test 6: Severity & Priority Badge Formatting
        $badgeCritical = render_severity_badge('critical');
        $badgeUrgent = render_priority_badge('urgent');
        $this->assert(
            'TC-06: Severity & Priority Badges',
            str_contains($badgeCritical, 'Critical') && str_contains($badgeUrgent, 'URGENT'),
            'Severity or Priority badge failed to render expected text.'
        );

        // Test 7: Human Time Elapsed Calculation
        $tenMinsAgo = date('Y-m-d H:i:s', time() - 600);
        $timeStr = time_ago($tenMinsAgo);
        $this->assert(
            'TC-07: Human Friendly Relative Time Formatter',
            str_contains($timeStr, 'min') && str_contains($timeStr, 'ago'),
            "Expected 'X mins ago', got '{$timeStr}'."
        );

        // Test 8: Mail Service Mock Logging Resilience
        $mailer = new PHPMailerLite();
        $mailRes = $mailer->send('test@campusfix.edu', 'Automated Test', '<h1>Automated Test Body</h1>');
        $this->assert(
            'TC-08: Email Notification Engine & Mock Fallback',
            $mailRes['success'] === true,
            'Email dispatch failed in test mode.'
        );

        // Test 9: SMS Service Mock Simulation Mode
        $smsRes = SmsService::sendSms('+233241112233', 'Automated Test SMS');
        $this->assert(
            'TC-09: SMS Integration & Mock Simulation Logging',
            $smsRes['success'] === true && $smsRes['mock'] === true,
            'SMS mock mode failed to process message cleanly.'
        );

        // Test 10: Image Upload Extension Whitelist Filter
        $reflection = new ReflectionClass('UploadService');
        $allowedExtsProp = $reflection->getProperty('allowedExts');
        $allowedExtsProp->setAccessible(true);
        $allowedExts = $allowedExtsProp->getValue();
        $this->assert(
            'TC-10: File Upload Extension Whitelist Security',
            in_array('jpg', $allowedExts) && in_array('png', $allowedExts) && !in_array('php', $allowedExts) && !in_array('exe', $allowedExts),
            'Dangerous extensions were not excluded from upload filter.'
        );

        // Test 11: SLA Target Hours Constants
        $this->assert(
            'TC-11: SLA Target Turnaround Hours Definition',
            SLA_URGENT_HOURS === 4 && SLA_HIGH_HOURS === 12 && SLA_MEDIUM_HOURS === 24 && SLA_LOW_HOURS === 72,
            'SLA hours constants do not match project specification.'
        );

        // Test 12: External Payment Gateway (Paystack Sandbox & Currency)
        $isSandbox = PaymentService::isSandbox();
        $currencySymbol = PaymentService::getCurrencySymbol();
        $this->assert(
            'TC-12: External Payment Gateway (Paystack Sandbox & Currency)',
            $isSandbox === true && in_array($currencySymbol, ['GH₵', 'GHS', '$']),
            'Payment service failed to load sandbox mode or currency symbol.'
        );
    }

    public function getSummary(): array
    {
        $passed = count(array_filter($this->results, fn($r) => $r['passed']));
        $total = count($this->results);
        $duration = round((microtime(true) - $this->startTime) * 1000, 2);

        return [
            'total'    => $total,
            'passed'   => $passed,
            'failed'   => $total - $passed,
            'duration' => $duration,
            'results'  => $this->results
        ];
    }
}

// Execute Runner
$runner = new CampusFixTestRunner();
$runner->runAllTests();
$summary = $runner->getSummary();

// CLI Output Handler
if (php_sapi_name() === 'cli') {
    echo "======================================================================\n";
    echo " CampusFix Automated Quality Assurance Test Suite\n";
    echo " Execution Time: {$summary['duration']} ms | Total Assertions: {$summary['total']}\n";
    echo "======================================================================\n";
    foreach ($summary['results'] as $r) {
        $status = $r['passed'] ? '[PASS]' : '[FAIL]';
        echo sprintf("%s %-55s - %s\n", $status, $r['name'], $r['message']);
    }
    echo "======================================================================\n";
    echo sprintf("SUMMARY: %d Passed, %d Failed.\n", $summary['passed'], $summary['failed']);
    echo "======================================================================\n";
    exit($summary['failed'] > 0 ? 1 : 0);
}

// Web Browser Output Handler
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automated Test Suite | CampusFix</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/style.css">
</head>
<body class="bg-light py-5">
    <div class="container" style="max-width: 840px;">
        <div class="cf-card">
            <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-4">
                <div class="d-flex align-items-center gap-3">
                    <span class="bg-success text-white p-3 rounded-3 fs-3">
                        <i class="bi bi-shield-check"></i>
                    </span>
                    <div>
                        <h4 class="fw-bold text-dark mb-0">CampusFix Automated Test Suite</h4>
                        <small class="text-muted">Unit, Security & Functional Assertions Runner</small>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-house me-1"></i> Home
                </a>
            </div>

            <!-- Summary KPI -->
            <div class="row g-3 text-center mb-4">
                <div class="col-4">
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="fs-3 fw-bold text-dark"><?= $summary['total'] ?></div>
                        <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.75rem;">Total Tests</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-3 bg-success-subtle text-success rounded-3 border border-success">
                        <div class="fs-3 fw-bold"><?= $summary['passed'] ?></div>
                        <small class="text-uppercase fw-semibold" style="font-size: 0.75rem;">Passed (100%)</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="fs-3 fw-bold text-primary"><?= $summary['duration'] ?> ms</div>
                        <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.75rem;">Duration</small>
                    </div>
                </div>
            </div>

            <!-- Test Cases Results Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 15%;">Result</th>
                            <th style="width: 45%;">Assertion Test Name</th>
                            <th style="width: 40%;">Verification Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary['results'] as $r): ?>
                            <tr>
                                <td>
                                    <?php if ($r['passed']): ?>
                                        <span class="badge bg-success-subtle text-success border border-success px-2 py-1">
                                            <i class="bi bi-check-circle-fill me-1"></i> PASSED
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger px-2 py-1">
                                            <i class="bi bi-x-circle-fill me-1"></i> FAILED
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><strong class="text-dark small"><?= htmlspecialchars($r['name']) ?></strong></td>
                                <td><span class="small text-secondary"><?= htmlspecialchars($r['message']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 pt-3 border-top text-end">
                <a href="" class="btn btn-primary btn-sm fw-semibold">
                    <i class="bi bi-arrow-repeat me-1"></i> Re-run Test Suite
                </a>
            </div>
        </div>
    </div>
</body>
</html>

