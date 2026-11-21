# Project Development Plan & 8-Week Agile Sprint Plan
## CampusFix – Campus Maintenance and IT Fault Reporting & Management System

**Document Version:** 1.0.0  
**Methodology:** Agile / Scrum Framework  
**Project Duration:** 8 Weeks (4 Sprints × 2 Weeks per Sprint)  
**Academic Target:** University Software Engineering Group Capstone  

---

## 1. Project Lifecycle Overview

CampusFix follows an iterative Agile Scrum methodology. The 8-week development timeline is divided into **four 2-week sprints**, each culminating in a functional, demonstrable milestone.

```
+---------------------------------------------------------------------------------------+
|                                8-WEEK SPRINT ROADMAP                                  |
+-------------------+-------------------+-----------------------+-----------------------+
| SPRINT 1 (Wk 1-2) | SPRINT 2 (Wk 3-4) | SPRINT 3 (Wk 5-6)     | SPRINT 4 (Wk 7-8)     |
+-------------------+-------------------+-----------------------+-----------------------+
| - Requirements    | - Auth & Sessions | - Ticket Pipeline     | - Admin Analytics     |
| - 3NF Schema & SQL| - RBAC Guardrails | - Tech Work Queue     | - Email / Mock SMS    |
| - ERD & Use Cases | - Design System   | - Investigation Notes | - Security Audit      |
| - Repo Architecture| - Student Report  | - Resolution Actions  | - QA Integration Tests|
| - UI Wireframing  | - Media Uploads   | - Reopen Loop         | - Final Demo Prep     |
+-------------------+-------------------+-----------------------+-----------------------+
```

---

## 2. Granular 8-Week Sprint Breakdown

### Sprint 1 (Weeks 1 & 2): Foundation, Architecture & Database Design (Current Stage 1)
**Sprint Goal:** Complete all technical specifications, normalize the relational schema, generate importable database seeds, establish GitHub collaboration conventions, and construct the base directory structure.

- **Week 1: Requirements Engineering & Database Architecture**
  - Synthesize university maintenance workflows into formal user stories.
  - Engineer IEEE 830-compliant Software Requirements Specification (`docs/SRS.md`).
  - Architect 3NF normalized database schema with foreign keys, cascading constraints, and indexes.
  - Generate production-ready SQL initialization and seed script (`database/campusfix.sql`).
- **Week 2: Diagrams, Git Setup & UI Design System Blueprint**
  - Author Mermaid ERD and behavioral sequence diagrams (`docs/ERD.md`, `docs/USE_CASE_DIAGRAMS.md`).
  - Formulate modular MVC-layered folder structure (`app/`, `views/`, `assets/`, `docs/`).
  - Establish GitHub collaboration protocols, branch protection, and member module allocation.
  - **Milestone 1 Deliverable:** Stage 1 architectural sign-off package ready for evaluation.

---

### Sprint 2 (Weeks 3 & 4): Core Engine, Authentication & Student Reporting Module
**Sprint Goal:** Implement the security infrastructure, session-based RBAC, SaaS UI design system, student fault reporting form, and multi-image upload pipeline.

- **Week 3: Security Framework & Authentication Engine**
  - Implement PDO database wrapper with connection pooling and error suppression.
  - Construct authentication controller: Student registration, bcrypt password hashing, login, logout, and password reset.
  - Build session management and RBAC middleware (`AuthMiddleware.php`, `CsrfMiddleware.php`).
  - Develop global Bootstrap 5 SaaS layout (`views/layouts/header.php`, `sidebar.php`, `navbar.php`, `footer.php`).
- **Week 4: Fault Submission & Secure Upload Pipeline**
  - Develop student dashboard displaying active and resolved tickets.
  - Implement the fault reporting wizard with dynamic building/floor/room dropdowns.
  - Implement `UploadService.php`: Validate image MIME types, check 5MB limits, sanitize filenames, and store securely.
  - Generate unique formatted ticket IDs (`CF-2026-XXXX`) upon insert.
  - **Milestone 2 Deliverable:** Students can register, log in, file maintenance reports with multiple photos, and view them in their personal history.

---

### Sprint 3 (Weeks 5 & 6): Technician Dashboard & Ticket Lifecycle Management
**Sprint Goal:** Implement technician fieldwork tools, status state machine transitions, technical notes, resolution actions, and student confirmation/reopening loops.

- **Week 5: Technician Work Order Portal**
  - Build technician portal dashboard displaying assigned jobs categorized by priority.
  - Implement filtering by category, building, and severity.
  - Build the comprehensive ticket inspection view: High-resolution image zoom, reporter details, and campus location map.
  - Implement status transition: `Assigned` -> `In Progress`.
- **Week 6: Resolution Workflow & Student Feedback Loop**
  - Build technician "Mark Resolved" modal: Mandatory repair notes, replacement parts logged, and completion photo upload.
  - Record full transition history in `ticket_status_history` table.
  - Build student confirmation interface: 1-to-5 star rating and feedback submission.
  - Implement ticket reopening logic: If problem recurs within 7 days, ticket reverts to `Reopened` with user comments.
  - **Milestone 3 Deliverable:** Complete closed-loop ticket lifecycle from submission through technician repair to user confirmation.

---

### Sprint 4 (Weeks 7 & 8): Admin Control, Analytics, Notifications, Testing & Final Polish
**Sprint Goal:** Construct the administrative control center, interactive Chart.js analytics, multi-channel notification engine, security hardening, and final presentation readiness.

- **Week 7: Admin Command Center, Analytics & Notifications**
  - Build master admin ticket triage desk: Reassign technician, override priority, cancel duplicate tickets.
  - Implement dynamic Chart.js dashboards:
    - Fault distribution by category (Doughnut).
    - Incidents by campus building (Bar chart).
    - Mean Time to Resolution (MTTR) trends.
    - Technician workload and open vs. closed ratios.
  - Implement `NotificationService.php`: In-app bell dropdown, PHPMailer SMTP email dispatch, and resilient Mock SMS dispatcher.
- **Week 8: System Hardening, Integration Testing & Presentation Preparation**
  - Comprehensive testing: Form validation, SQL injection attempts, XSS filtering, CSRF verification, and edge-case testing.
  - Optimize database queries with EXPLAIN plans.
  - Seed rich realistic demonstration scenarios.
  - Record backup walkthrough video and prepare slide deck for academic defense.
  - **Milestone 4 Deliverable:** Fully deployed, tested, and demonstrated enterprise-grade CampusFix platform.

---

## 3. Definition of Done (DoD)

A user story or feature branch is considered "Done" and eligible for merging into `develop` only when:
1. **Functional Completeness:** The feature fulfills all acceptance criteria specified in the SRS.
2. **Security Compliance:** All database queries use PDO prepared statements; inputs are validated; outputs are escaped with `htmlspecialchars()`; CSRF tokens are verified on POST.
3. **Responsive UI:** View renders seamlessly on mobile (375px), tablet (768px), and desktop (1200px+) without horizontal overflow.
4. **Clean Code:** Code follows PSR-12 formatting with meaningful variable names and descriptive comments.
5. **No Regressions:** Peer-reviewed via GitHub Pull Request by at least one other team member.

---

## 4. Risk Assessment & Mitigation Matrix

| Risk Identified | Likelihood | Impact | Mitigation Strategy |
| :--- | :--- | :--- | :--- |
| **XAMPP Environment Inconsistencies** | Medium | High | Standardize PHP version (8.2+) across all 5 team laptops. Maintain a single source-of-truth `campusfix.sql` script. |
| **Third-Party SMTP / SMS Gateway Failure** | High | Medium | Implement an automatic **Mock Mode** for both email and SMS that logs dispatch payloads to the database when live credentials are unavailable. |
| **Git Merge Conflicts in Shared Views** | High | Medium | Modularize views into granular partials (`header.php`, `navbar.php`, `sidebar.php`, `ticket-card.php`) and strictly isolate feature branches. |
| **File Upload Vulnerabilities** | Medium | Critical | Strictly enforce server-side MIME type verification (`image/jpeg`, `image/png`, `image/webp`), restrict file sizes to 5MB, and rename files with random hashes. |
| **Scope Creep during Sprints** | Medium | High | Adhere strictly to the SRS requirements; defer non-essential features to a "Future Roadmap" backlog. |
