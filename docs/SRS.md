# Software Requirements Specification (SRS)
## CampusFix – Campus Maintenance and IT Fault Reporting & Management System

**Document Version:** 1.0.0  
**Status:** Approved for Implementation (Stage 1)  
**Standard:** IEEE Std 830-1998 Compliant  
**Project Lead:** Kwame Bonsu & Engineering Group Team  
**Institution:** University Campus Technology Project  

---

## Table of Contents
1. [Introduction](#1-introduction)
   - 1.1 Purpose
   - 1.2 Scope of the System
   - 1.3 Definitions, Acronyms, and Abbreviations
   - 1.4 References
   - 1.5 Overview of the Document
2. [Overall Description](#2-overall-description)
   - 2.1 Product Perspective
   - 2.2 Product Functions
   - 2.3 User Classes and Characteristics
   - 2.4 Operating Environment
   - 2.5 Design and Implementation Constraints
   - 2.6 Assumptions and Dependencies
3. [System Features & Functional Requirements](#3-system-features--functional-requirements)
   - 3.1 User Authentication & Authorization (FR-AUTH)
   - 3.2 Fault Reporting & Incident Submission (FR-REPORT)
   - 3.3 Ticket Tracking & Status Lifecycle (FR-TRACK)
   - 3.4 Technician Work Order Management (FR-TECH)
   - 3.5 Administrative Triage & Resource Allocation (FR-ADMIN)
   - 3.6 Analytics, Reporting & Recurring Fault Detection (FR-ANALYTICS)
   - 3.7 Multi-Channel Notification Engine (FR-NOTIF)
   - 3.8 Security & Audit Logging (FR-SEC)
4. [External Interface Requirements](#4-external-interface-requirements)
   - 4.1 User Interfaces (UI/UX)
   - 4.2 Hardware Interfaces
   - 4.3 Software Interfaces
   - 4.4 Communication Interfaces
5. [Non-Functional Requirements (NFRs)](#5-non-functional-requirements-nfrs)
   - 5.1 Performance Requirements
   - 5.2 Security & Data Protection Requirements
   - 5.3 Reliability, Availability & Fault Tolerance
   - 5.4 Usability & Accessibility
   - 5.5 Maintainability, Portability & Extensibility
6. [Business Rules & Workflows](#6-business-rules--workflows)
   - 6.1 Ticket Lifecycle State Machine
   - 6.2 Service Level Agreement (SLA) Matrix
   - 6.3 Media Upload Constraints

---

## 1. Introduction

### 1.1 Purpose
This Software Requirements Specification (SRS) establishes the foundational requirements for **CampusFix**, a web-based campus facility maintenance and IT fault reporting and management system. It provides engineering specifications for developers, database designers, UI/UX engineers, quality assurance testers, and academic evaluators.

### 1.2 Scope of the System
CampusFix bridges the communication gap between university facility users (students, faculty, administrative staff) and service delivery units (IT Directorate, Physical Works, Electrical, and Estate Maintenance). The application enables:
- Rapid reporting of infrastructural and technological breakdowns via responsive web interfaces.
- Precise location tagging (Building, Floor, Room, Area Details).
- Photographic evidence uploads for accelerated diagnosis.
- Automated ticketing with human-readable tracking identifiers (`CF-YYYY-XXXX`).
- Centralized administrative triage, prioritization, and technician dispatching.
- Technician status updates, investigation logs, and repair documentation.
- Real-time status tracking and multi-channel notifications (In-App, Email via PHPMailer, SMS mock/gateway).
- Metric-driven analytics identifying recurring failures, overdue repairs, and technician workloads.

### 1.3 Definitions, Acronyms, and Abbreviations
- **CF:** CampusFix
- **RBAC:** Role-Based Access Control
- **PDO:** PHP Data Objects (Database abstraction and secure prepared statements)
- **SLA:** Service Level Agreement (Target turnaround time based on priority)
- **SMTP:** Simple Mail Transfer Protocol
- **CSRF:** Cross-Site Request Forgery
- **XSS:** Cross-Site Scripting
- **ERD:** Entity-Relationship Diagram

### 1.4 References
- IEEE Std 830-1998: Recommended Practice for Software Requirements Specifications.
- OWASP Top 10 Web Application Security Vulnerabilities (2021).
- PHP 8+ Documentation & Standard Recommendations (PSR-1, PSR-4, PSR-12).
- Bootstrap 5.3 Documentation & Component System.

### 1.5 Overview of the Document
The remainder of this specification describes the system from high-level operational concepts down to granular functional and non-functional requirements.

---

## 2. Overall Description

### 2.1 Product Perspective
CampusFix replaces archaic paper-based complaint registers, informal WhatsApp messages, and fragmented phone calls with a centralized, database-driven web platform. It operates in an on-premises or cloud-hosted LAMP/XAMPP environment accessible from campus desktop workstations, tablets, and smartphones.

### 2.2 Product Functions
```
+--------------------------------------------------------------------+
|                             CampusFix                              |
+-------------------+--------------------+---------------------------+
| Student / Staff   | Technician         | Administrator             |
+-------------------+--------------------+---------------------------+
| - Register/Login  | - Work Dashboard   | - Master Incident Desk    |
| - Submit Ticket   | - Filter Assigned  | - Ticket Assignment/SLA   |
| - Multi-Photo Up. | - Photo Inspection | - Category/Location Admin |
| - Track Progress  | - Status Lifecycle | - User Management         |
| - Resolution Conf.| - Repair Action Log| - Analytics & Charts      |
| - Feedback/Reopen | - Internal Notes   | - Audit & System Logs     |
+-------------------+--------------------+---------------------------+
```

### 2.3 User Classes and Characteristics
1. **Student / Campus Reporter:** General campus population reporting infrastructure defects. Requires an intuitive, zero-training interface with instant submission feedback.
2. **Field Technician:** Skilled university artisans and IT support officers executing repairs. Requires a lightweight mobile-friendly view of assigned tasks, diagnostic details, and repair completion forms.
3. **System Administrator:** Facilities managers and IT helpdesk leads responsible for oversight, resource balancing, technician accountability, and strategic campus infrastructure analytics.

### 2.4 Operating Environment
- **Server OS:** Cross-platform (Windows XAMPP / Linux Ubuntu Server / macOS).
- **Web Server:** Apache 2.4+ with `mod_rewrite` enabled.
- **Backend Runtime:** PHP 8.0 or higher with PDO MySQL, OpenSSL, and GD/Imagick extensions.
- **Database Engine:** MySQL 8.0+ or MariaDB 10.4+ (InnoDB engine).
- **Client Browsers:** Google Chrome 90+, Mozilla Firefox 88+, Safari 14+, Microsoft Edge 90+, Mobile Safari/Chrome.

### 2.5 Design and Implementation Constraints
- Must function reliably in local offline/intranet university environments.
- Must execute completely under standard XAMPP stack without requiring paid external cloud services.
- SMS module must support zero-credential Mock Simulation Mode without breaking workflow.
- Clean vanilla PHP 8+ modular architecture with PDO to satisfy university academic grading standards.

### 2.6 Assumptions and Dependencies
- Campus users possess basic literacy in smartphone or computer browser usage.
- The web server maintains read/write access to `assets/uploads/tickets` and `assets/uploads/avatars`.

---

## 3. System Features & Functional Requirements

### 3.1 User Authentication & Authorization (FR-AUTH)
- **FR-AUTH-1 (Registration):** New student/staff users shall register with full name, university email, phone number, academic department, and password.
- **FR-AUTH-2 (Password Security):** Passwords shall be hashed using standard `PASSWORD_BCRYPT` with cost factor 10+.
- **FR-AUTH-3 (Login & Session):** System shall authenticate users against the `users` table and issue secure session tokens (`HttpOnly`, `SameSite=Lax`).
- **FR-AUTH-4 (RBAC Enforcement):** Unauthorized access to privileged routes (`/admin/*`, `/technician/*`) shall trigger a 403 Forbidden redirection.
- **FR-AUTH-5 (CSRF Protection):** All state-modifying requests (POST/PUT/DELETE) must include a cryptographically random, per-session CSRF token.

### 3.2 Fault Reporting & Incident Submission (FR-REPORT)
- **FR-REPORT-1 (Categorization):** Users shall select from pre-configured categories (e.g., Wi-Fi, Computers, Projectors, Electrical, Plumbing).
- **FR-REPORT-2 (Location Hierarchy):** Users shall choose a standardized Building, Floor, and Room, with an optional granular free-text field for pinpoint guidance.
- **FR-REPORT-3 (Severity Indicator):** Users shall indicate perceived fault impact (`Low`, `Medium`, `High`, `Critical`).
- **FR-REPORT-4 (Multiple Image Uploads):** Users can attach up to 3 visual proofs (JPEG/PNG/WebP, max 5MB each). System validates MIME types and stores sanitized random file names.
- **FR-REPORT-5 (Unique Ticket Generation):** Upon submission, system automatically generates a formatted tracking ID (`CF-YYYY-XXXX`) and assigns `Pending` status.

### 3.3 Ticket Tracking & Status Lifecycle (FR-TRACK)
- **FR-TRACK-1 (Status Pipeline):** Tickets shall progress through:
  `Pending` -> `Assigned` -> `In Progress` -> `Resolved` -> `Closed`.
- **FR-TRACK-2 (Reopening Mechanism):** If a repair is unsatisfactory, the reporter may reopen the ticket within 7 calendar days with explanatory feedback.
- **FR-TRACK-3 (Status History Audit):** Every transition creates an immutable record in `ticket_status_history` logging old status, new status, timestamp, and changing user.
- **FR-TRACK-4 (Public Comments):** Reporters and technicians can exchange clarifications on the public ticket comment stream.

### 3.4 Technician Work Order Management (FR-TECH)
- **FR-TECH-1 (Technician Queue):** Technicians shall access a tailored dashboard listing only tickets dispatched to their profile.
- **FR-TECH-2 (Queue Filtering):** Technicians can filter work orders by priority, severity, category, or status.
- **FR-TECH-3 (Job Acceptance & Start):** Technicians can update ticket status from `Assigned` to `In Progress` upon commencing physical work.
- **FR-TECH-4 (Resolution Documentation):** When marking `Resolved`, technician must submit a mandatory `resolution_notes` narrative and optional proof-of-fix photo.
- **FR-TECH-5 (Internal Notes):** Technicians can write private internal notes visible only to fellow staff and administrators.

### 3.5 Administrative Triage & Resource Allocation (FR-ADMIN)
- **FR-ADMIN-1 (Master Ticket Desk):** Administrators can view, search, and paginate through all tickets across all campus facilities.
- **FR-ADMIN-2 (Technician Dispatch):** Admins can assign or reassign tickets to qualified technicians, attaching specific dispatch instructions.
- **FR-ADMIN-3 (Priority Override):** Admins can adjust ticket priority (`Low`, `Medium`, `High`, `Urgent`) based on institutional urgency.
- **FR-ADMIN-4 (Category & Location CRUD):** Admins can create, update, or deactivate categories and campus buildings/rooms.
- **FR-ADMIN-5 (User Management):** Admins can manage user accounts, assign roles, and toggle user active/suspended status.

### 3.6 Analytics, Reporting & Recurring Fault Detection (FR-ANALYTICS)
- **FR-ANALYTICS-1 (KPI Cards):** Real-time aggregate count of Total Tickets, Pending Triage, Active Repairs, Resolved Tickets, and Overdue Incidents.
- **FR-ANALYTICS-2 (Recurring Issues Engine):** System calculates incident frequency grouped by building and category, highlighting hardware or infrastructure hot spots.
- **FR-ANALYTICS-3 (Workload Distribution):** Visual bar chart displaying open vs. resolved job volume per active technician.
- **FR-ANALYTICS-4 (Mean Time to Resolution - MTTR):** System calculates average turnaround hours from ticket creation to `resolved_at`.
- **FR-ANALYTICS-5 (SLA Breach Tracking):** Highlights tickets exceeding target resolution thresholds based on priority.

### 3.7 Multi-Channel Notification Engine (FR-NOTIF)
- **FR-NOTIF-1 (Trigger Events):** Notifications dispatch on:
  - New ticket submitted (Admin notified).
  - Ticket assigned (Technician and Reporter notified).
  - Status changed to In Progress / Resolved (Reporter notified).
  - Ticket reopened (Admin and Technician notified).
- **FR-NOTIF-2 (In-App Notifications):** Persisted in database, featuring an unread badge counter in the top navigation bar.
- **FR-NOTIF-3 (Email Integration):** Modular PHPMailer SMTP client with clean HTML templates.
- **FR-NOTIF-4 (SMS Integration & Mock Mode):** When live SMS gateway credentials are absent, the system routes notifications through a Mock Dispatcher that records payloads to database logs without failing or falsifying carrier delivery.

### 3.8 Security & Audit Logging (FR-SEC)
- **FR-SEC-1 (Prepared Statements):** 100% of SQL executions utilize PDO parameterized statements with zero raw string concatenation.
- **FR-SEC-2 (Output Encoding):** All dynamic browser outputs pass through `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- **FR-SEC-3 (Audit Trail):** Critical system actions (logins, assignments, status changes, deletions) are recorded in `activity_logs` with client IP address and user-agent.

---

## 4. External Interface Requirements

### 4.1 User Interfaces (UI/UX)
- Modern SaaS Helpdesk visual design.
- **Primary Color:** Deep University Navy (`#0f172a` / `#1e293b`).
- **Accent/Brand:** Royal Blue (`#2563eb`).
- **Semantic Colors:** Green (`#10b981` Resolved/Active), Amber (`#f59e0b` Pending/Medium), Red (`#ef4444` Critical/Urgent), Blue (`#3b82f6` In Progress).
- **Components:** Sidebar navigation, top bar with user profile dropdown & notification bell, stat cards with iconography, clean responsive data tables, modal dialogs for status updates, and toast messages for operation results.

### 4.2 Hardware Interfaces
No specialized hardware required. Compatible with standard cameras/mobile lenses for photo uploads.

### 4.3 Software Interfaces
- **Database Engine:** MySQL 8.0+ / MariaDB 10.4 via PDO.
- **Email Gateway:** SMTP via PHPMailer (Gmail, Mailtrap, SendGrid, or local SMTP).
- **SMS Gateway:** REST API integration (Hubtel, Arkesel, Twilio, or native Mock Gateway).

### 4.4 Communication Interfaces
- HTTPS (TLS 1.2/1.3) recommended for production; HTTP supported on local development `http://localhost`.

---

## 5. Non-Functional Requirements (NFRs)

### 5.1 Performance Requirements
- **Page Load Time:** Initial document load under 1.5 seconds on standard campus Wi-Fi.
- **Query Efficiency:** Indexed lookups for ticket lists, paginated at 15-25 records per view.
- **Database Concurrency:** Capable of supporting up to 200 concurrent active campus users on modest local hardware.

### 5.2 Security & Data Protection
- Strong password enforcement (minimum 8 characters).
- Protection against SQL Injection, XSS, CSRF, Session Fixation, and Path Traversal.
- Secure file storage: Uploaded images are renamed to UUID/SHA-256 strings and stored outside executable script paths.

### 5.3 Reliability & Fault Tolerance
- Graceful database connection failure handling with polite user-facing error screens.
- Mock notification resilience ensuring ticket state transitions succeed even if third-party SMTP or SMS gateways timeout.

### 5.4 Usability & Accessibility
- WCAG 2.1 Level AA color contrast compliance.
- Responsive mobile layout adapting smoothly down to 360px viewport widths.
- Human-friendly date formatting ("2 hours ago", "Yesterday at 3:15 PM").

### 5.5 Maintainability & Extensibility
- Clean layered architecture separating Controllers, Models, Views, and Services.
- Consistent PSR-12 code style with informative docblocks.

---

## 6. Business Rules & Workflows

### 6.1 Ticket Lifecycle State Machine
```
[User Submits] ---> (Pending)
                        |
                 [Admin Assigns]
                        v
                    (Assigned)
                        |
              [Technician Commences]
                        v
                  (In Progress)
                        |
               [Technician Fixes]
                        v
                   (Resolved)
                   /        \
       [User Confirms]    [User Reopens]
             v                  v
         (Closed)          (Reopened) ---> [Admin Reassigns]
```

### 6.2 Service Level Agreement (SLA) Matrix
| Priority | Target Response Window | Target Resolution Window | Default Notification Channels |
| :--- | :--- | :--- | :--- |
| **Urgent (Critical Hazard)** | Within 30 minutes | Within 4 hours | In-App, Email, SMS Alert |
| **High** | Within 2 hours | Within 12 hours | In-App, Email, SMS Alert |
| **Medium** | Within 4 hours | Within 24 hours | In-App, Email |
| **Low** | Within 8 hours | Within 72 hours | In-App |

### 6.3 Media Upload Constraints
- **Permitted File Formats:** `image/jpeg`, `image/png`, `image/webp`.
- **Prohibited Extensions:** `.php`, `.phtml`, `.exe`, `.sh`, `.js`, `.html`, `.svg`.
- **Maximum File Size:** 5 MB per individual image.
- **Max Files Per Ticket:** 3 images upon submission + 2 resolution proof photos upon technician completion.
