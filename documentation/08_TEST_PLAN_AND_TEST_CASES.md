# Test Plan & Quality Assurance Specifications
## CampusFix – Campus Maintenance and IT Fault Reporting & Management System

**Document Version:** 1.0.0  
**Testing Strategy:** Unit, Integration, Role-Based Security & User Acceptance Testing (UAT)  

---

## 1. Testing Objectives
The primary testing goals for CampusFix are:
1. Verify 100% adherence to functional requirements outlined in the SRS.
2. Ensure bulletproof Role-Based Access Control (RBAC) preventing horizontal and vertical privilege escalation.
3. Validate data integrity, PDO parameterized queries, and input/output sanitation.
4. Guarantee fault tolerance when external third-party services (SMTP email / SMS gateways) are unreachable.
5. Provide clear test execution scripts suitable for academic evaluation and demonstration.

---

## 2. Test Environment
- **Web Server:** Apache 2.4 on XAMPP (Windows 10/11).
- **PHP Version:** PHP 8.2+ with PDO, OpenSSL, and cURL extensions.
- **Database:** MySQL 8.0+ / MariaDB 10.4 (`campusfix`).
- **Client Browsers:** Google Chrome, Mozilla Firefox, Microsoft Edge.

---

## 3. Comprehensive Test Case Matrix

| Test ID | Module | Test Scenario / Action | Input Data | Expected Result | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-AUTH-01** | Auth | Student Registration with valid details | Name, valid email, phone, password > 8 chars | User record inserted; password hashed with bcrypt; redirect to login | Planned |
| **TC-AUTH-02** | Auth | Registration with duplicate email | Existing student email address | Form rejected; inline error: "Email address already registered" | Planned |
| **TC-AUTH-03** | Auth | Login with incorrect password | Valid email, incorrect password | Authentication rejected; error: "Invalid email or password" | Planned |
| **TC-AUTH-04** | Security | Role-Based Access Control Guardrail | Student attempts accessing `/admin/dashboard.php` | Access blocked; HTTP 403 Forbidden or redirect with "Unauthorized" alert | Planned |
| **TC-AUTH-05** | Security | CSRF Protection Verification | POST submission with invalid or missing CSRF token | Request rejected with HTTP 403; no database write occurs | Planned |
| **TC-TICK-01** | Reporting | Submit valid fault report with 2 JPG images | Valid category, building, floor, room, severity, 2 valid JPGs | Ticket created with unique ID `CF-2026-XXXX`; images stored in uploads/ | Planned |
| **TC-TICK-02** | Reporting | Submit report with disallowed file type | Upload `.exe` or `.php` file disguised as photo | Upload rejected; error: "Only JPG, PNG, and WEBP formats allowed" | Planned |
| **TC-TICK-03** | Reporting | Submit image exceeding 5MB size limit | Image file size 7.2 MB | Upload rejected; error: "File exceeds 5MB size limit" | Planned |
| **TC-ADMIN-01**| Triage | Admin assigns ticket to technician | Select Ticket, choose Technician Samuel Osei | `assigned_to` updated; status changes to `assigned`; history logged | Planned |
| **TC-ADMIN-02**| Triage | Priority override and SLA deadline | Change priority from Medium to Urgent | Priority updated; audit log entry recorded in `activity_logs` | Planned |
| **TC-TECH-01** | Technician| Technician updates status to In Progress | Technician clicks "Start Work" | Ticket status updates to `in_progress`; student alerted | Planned |
| **TC-TECH-02** | Technician| Technician marks ticket Resolved | Resolution notes submitted (min 20 chars) | Ticket status updates to `resolved`; `resolved_at` timestamp recorded | Planned |
| **TC-STUD-01** | Student | Student confirms resolution & rates service | 5-Star rating and positive comment | Ticket status updates to `closed`; satisfaction rating stored | Planned |
| **TC-STUD-02** | Student | Student reopens incomplete ticket | Problem recurrence description | Ticket status updates to `reopened`; admin and technician notified | Planned |
| **TC-NOTIF-01**| Notif | Mock SMS mode behavior when no API key | Ticket status changes; `sms_mock_mode=1` | Notification logged in `notifications` table as `mock_delivered` without error | Planned |
| **TC-ANALYT-01**|Analytics| Dashboard KPI calculation from live DB | Query real ticket counts vs dashboard counters | Numbers match database query aggregates exactly (no hardcoded data) | Planned |

---

## 4. Automated Testing Strategy

To satisfy university project assessment requirements for automated verification, a standalone automated test runner script will be placed in `tests/test_runner.php`.

The test script will:
1. Connect via PDO to the `campusfix` database.
2. Execute automated assertions on:
   - Database connection integrity.
   - Password hashing and verification routines.
   - Input sanitization against XSS payloads (`<script>alert(1)</script>`).
   - Image MIME type validation logic.
   - Status transition state-machine rules.
3. Output a clean CLI or web-based pass/fail summary report suitable for inclusion in academic project submissions.

