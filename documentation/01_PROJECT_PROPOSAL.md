# Project Proposal
## CampusFix – Campus Maintenance and IT Fault Reporting & Management System

**Academic Year:** 2026/2027  
**Course:** Software Engineering Group Capstone Project  
**Target Institution:** University Campus  
**Project Lead:**  Samuel Kwame Bonsu & Engineering Group Team  

---

## 1. Executive Summary
University campuses host thousands of students, faculty members, and administrative staff who rely continuously on physical and technological infrastructure. In everyday campus life, equipment breakdowns occur routinely: Wi-Fi routers crash, classroom projectors overheat during lectures, laboratory computers experience hardware failures, electrical outlets short-circuit, and sanitary washrooms develop plumbing leaks.

Currently, most university campuses suffer from fragmented, archaic fault reporting mechanisms. Incidents are logged in manual physical paper logbooks at security desks, buried in generic administrative email inboxes, or shared informally across messaging apps like WhatsApp. Consequently, maintenance requests are frequently misplaced, response times are slow, field technicians lack diagnostic context or photos before visiting sites, and students are left uninformed about repair progress.

**CampusFix** is a purpose-built, secure, database-driven web application designed to modernize and centralize campus fault reporting. It provides an intuitive self-service portal for students and staff, an actionable work order queue for technicians, and a data-driven command dashboard for university administrators.

---

## 2. Problem Statement
The absence of a centralized digital maintenance tracking system leads to severe operational inefficiencies:
1. **Lack of Accountability:** Without timestamped digital records, it is impossible to track whether reported faults were triaged promptly or neglected.
2. **Inaccurate Fault Diagnosis:** Technicians often arrive on-site without prior knowledge of the exact hardware model, failure symptoms, or photographic evidence, leading to wasted round trips.
3. **Absence of User Feedback:** Reporters have zero visibility into repair status and repeatedly submit duplicate complaints for the same broken equipment.
4. **No Institutional Memory or Analytics:** Facilities managers cannot identify recurring hardware failures, calculate Mean Time to Resolution (MTTR), or evaluate technician workload distribution.

---

## 3. Project Objectives
The overarching aim of CampusFix is to deliver a functional, production-quality web platform that solves these operational bottlenecks.

### Specific Objectives:
- **Digitalize Fault Reporting:** Allow students and staff to submit geotagged incident reports with photos, category classifications, and severity indicators in under 60 seconds.
- **Automate Tracking & Ticketing:** Issue distinct tracking numbers (`CF-2026-0001`) and enforce an auditable status workflow (`Pending` ➔ `Assigned` ➔ `In Progress` ➔ `Resolved` ➔ `Closed`).
- **Empower Field Technicians:** Provide a dedicated dashboard where technicians can inspect high-resolution fault images, log investigation notes, and document repairs.
- **Enable Administrative Oversight:** Equip managers with real-time analytics to identify recurring faults, monitor overdue incidents, and balance technician task allocations.
- **Establish Multi-Channel Communication:** Automatically alert users via in-app notifications, SMTP emails (PHPMailer), and SMS (with integration-ready mock mode).

---

## 4. Scope and Limitations
### In-Scope:
- Role-Based Access Control for three roles: Students/Reporters, Technicians, and Administrators.
- Multi-photo upload validation and secure storage.
- Interactive Chart.js analytics for administrative insights.
- Notification dispatch pipeline supporting in-app, SMTP email, and SMS mock logging.
- Comprehensive security measures (CSRF, PDO prepared statements, XSS output encoding, bcrypt password hashing).

### Out-of-Scope (Future Enhancements):
- Native Android/iOS mobile applications (the web application is fully mobile-responsive).
- GPS-based campus mapping (uses structured campus building/floor/room dropdowns).
- Direct inventory procurement/ERP purchasing integrations.

---

## 5. Feasibility Analysis
- **Technical Feasibility:** Built on industry-standard open-source web technologies (PHP 8+, MySQL, Bootstrap 5, Apache) supported natively by standard XAMPP environments without requiring paid subscriptions.
- **Operational Feasibility:** Minimal learning curve; clean SaaS UI requires no formal training for students or university artisans.
- **Economic Feasibility:** Zero software licensing costs; runs on local campus intranet infrastructure or low-cost virtual private servers.

