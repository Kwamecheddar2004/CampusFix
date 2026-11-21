# Quality Assurance Test Results & Verification Report
## CampusFix – Automated and Functional Verification Suite

**Document Version:** 1.0.0  
**Test Execution Date:** 2026-11-21  
**Environment:** PHP 8.2+ | Apache 2.4 | MySQL 8.0+ / MariaDB 10.4 | XAMPP  
**Test Suite Script:** `tests/test_runner.php`  

---

## 1. Executive QA Summary

The CampusFix platform was subjected to automated unit, security, and functional verification assertions. All automated test cases passed with a **100% success rate (11 Passed, 0 Failed)** in **224.48 milliseconds**.

```
======================================================================
 CampusFix Automated Quality Assurance Test Suite
 Execution Time: 224.48 ms | Total Assertions: 11
======================================================================
[PASS] TC-01: Bcrypt Password Hashing & Verification           - Passed
[PASS] TC-02: Output Escaping & XSS Protection                 - Passed
[PASS] TC-03: CSRF Token Generation & Cryptographic Length     - Passed
[PASS] TC-04: Ticket ID Sequential Numbering Format (CF-YYYY)  - Passed
[PASS] TC-05: Visual Status Badge Indicators                   - Passed
[PASS] TC-06: Severity & Priority Badges                       - Passed
[PASS] TC-07: Human Friendly Relative Time Formatter           - Passed
[PASS] TC-08: Email Notification Engine & Mock Fallback        - Passed
[PASS] TC-09: SMS Integration & Mock Simulation Logging        - Passed
[PASS] TC-10: File Upload Extension Whitelist Security         - Passed
[PASS] TC-11: SLA Target Turnaround Hours Definition           - Passed
======================================================================
SUMMARY: 11 Passed, 0 Failed (100% Pass Rate).
======================================================================
```

---

## 2. Granular Test Execution Ledger

| Test ID | Module | Verification Scope | Tested Behavior | Status |
| :--- | :--- | :--- | :--- | :--- |
| **TC-01** | Security | `password_hash()` / `password_verify()` | Verified that passwords are encrypted using `PASSWORD_BCRYPT` with cost factor 10, valid passwords verify true, and incorrect passwords fail. | **PASSED** |
| **TC-02** | Security | `sanitize()` | Injected `<script>alert("XSS")</script>` payload. Verified output is safely encoded to `&lt;script&gt;` with `ENT_QUOTES, 'UTF-8'`. | **PASSED** |
| **TC-03** | Security | `csrf_token()` / `verify_csrf_token()` | Verified token generation produces 64-character hexadecimal string, matches session hash, and rejects spoofed tokens. | **PASSED** |
| **TC-04** | Core Engine | Ticket Number Generator | Validated regex format `^CF-\d{4}-\d{4}$` ensuring standard format `CF-2026-0001`. | **PASSED** |
| **TC-05** | UI Engine | `render_status_badge()` | Verified accurate Bootstrap badge color mappings for `pending` (amber), `assigned` (cyan), `in_progress` (blue), and `resolved` (green). | **PASSED** |
| **TC-06** | UI Engine | `render_severity_badge()` / `render_priority_badge()` | Confirmed `critical` severity maps to bold red hazard indicator and `urgent` priority renders urgent SLA badge. | **PASSED** |
| **TC-07** | Helpers | `time_ago()` | Verified relative time diff correctly formats timestamps ("10 mins ago", "2 hours ago"). | **PASSED** |
| **TC-08** | Mail Service | `PHPMailerLite` & Mock Mode | Dispatched test email payload. Verified payload is written to `logs/mail_mock.log` without socket errors when SMTP credentials are blank. | **PASSED** |
| **TC-09** | SMS Gateway | `SmsService` Mock Engine | Dispatched test SMS. Confirmed mock mode writes to `logs/sms_mock.log` and flags delivery as `mock_delivered` without carrier failures. | **PASSED** |
| **TC-10** | Upload Security | `UploadService` Whitelist | Inspected allowed extension whitelist. Confirmed `jpg`, `jpeg`, `png`, and `webp` are allowed, while `.php`, `.exe`, `.sh` are strictly prohibited. | **PASSED** |
| **TC-11** | Business Logic | SLA Threshold Constants | Confirmed turnaround window constants: Urgent (4h), High (12h), Medium (24h), and Low (72h). | **PASSED** |

---

## 3. Manual Functional Verification Matrix

In addition to automated runner tests, the following manual functional workflows were verified:
1. **Student Registration & Auto-Login:** Verified new student record inserts into `users` table, creates session, and redirects to student portal.
2. **Role-Based Access Control Guardrails:** Attempted direct access to `/admin/dashboard.php` as a student user; received `403 Forbidden` response.
3. **Multi-Image Upload Flow:** Uploaded 2 valid JPG photos; verified storage in `uploads/tickets/` with random timestamped names and verified relational row insertions into `ticket_images`.
4. **Technician Status Transitions:** Verified transition from `Assigned` to `In Progress` updates database, records status history row, and dispatches in-app notification to the reporter.
5. **Resolution & Closure Feedback Loop:** Marked ticket `Resolved` with resolution narrative; student confirmed resolution, submitted 5-star rating, and ticket status successfully updated to `Closed`.
6. **Reopening Loop:** Verified student can reopen unresolved tickets with an explanatory reason, reverting ticket status to `Reopened` and returning it to the technician queue.
7. **Chart.js Live Data Binding:** Confirmed administrative analytics charts render live data computed from MySQL queries without hardcoded numbers.

---

## 4. Conclusion
The CampusFix system fulfills all quality assurance and testing requirements specified in the project engineering syllabus. The automated test runner is accessible anytime via terminal (`php tests/test_runner.php`) or through the browser (`http://localhost/.../tests/test_runner.php`).

