# CampusFix – Campus Maintenance and IT Fault Reporting & Management System

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777bb4?logo=php&logoColor=white)](https://php.net)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952b3?logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479a1?logo=mysql&logoColor=white)](https://mysql.com)
[![License](https://img.shields.io/badge/License-Academic%20Project-blue.svg)]()

> A robust, database-driven, secure web application designed for university campuses to streamline the reporting, assignment, tracking, and resolution of IT and physical facility maintenance faults.

---

## 📌 Project Overview

**CampusFix** eliminates disorganized manual paper registers and fragmented messaging by providing a centralized helpdesk platform tailored to tertiary institutions. It bridges students, faculty, facility managers, and technical artisans through automated workflows, transparent tracking, and data-driven maintenance insights.

### Primary User Roles
- 🎓 **Student / Campus Reporter:** Submit fault reports with photos, categorize issues, pinpoint building/room locations, track real-time repair progress, and confirm resolution with satisfaction ratings.
- 🔧 **Field Technician:** View assigned work orders, filter tasks by urgency, inspect visual evidence, add technical investigation notes, log repair actions, and submit completion reports.
- 🛡️ **System Administrator:** Triage pending incidents, dispatch technicians, adjust priorities, manage categories and campus locations, monitor SLA compliance, and analyze recurring problems using interactive charts.

---

## 🚀 Key Features

1. **Intelligent Fault Reporting:**
   - Multi-tier location tagging: Campus Building ➔ Floor ➔ Room ➔ Exact Area Notes.
   - Comprehensive categories: Wi-Fi, Computer Labs, Projectors & AV, Printers, Electrical, Lighting, Plumbing, and Structural.
   - Severity rating (`Low`, `Medium`, `High`, `Critical`) with visual badge indicators.
   - Secure multi-image upload for visual fault proof.
   - Automatic unique human-readable ticket IDs (e.g., `CF-2026-0001`).

2. **Full Lifecycle Ticket Tracking:**
   - Workflow: `Pending` ➔ `Assigned` ➔ `In Progress` ➔ `Resolved` ➔ `Closed`.
   - Reopening mechanism if faults recur or repairs are incomplete.
   - Immutable audit trail recording timestamps, actor names, and status change notes.
   - Interactive public discussion thread between reporters and service staff.

3. **Technician Field Operations:**
   - Tailored queue of assigned jobs sorted by urgency.
   - Internal technical notes invisible to students.
   - Resolution documentation capturing actions taken and replacement parts used.

4. **Executive Admin Command Center:**
   - Live KPI statistic cards (Total, Pending, Active, Resolved, Overdue).
   - Interactive Chart.js analytics: Category breakdowns, building trouble spots, technician workload distribution, and Mean Time to Resolution (MTTR).
   - User and role management (Students, Technicians, Admins).

5. **Multi-Channel Notification Engine:**
   - Real-time in-app notification bell with unread badge counter.
   - Configurable PHPMailer SMTP email dispatch.
   - Resilient SMS gateway with a built-in **Mock Simulation Mode** that logs dispatch payloads to the database when external API keys are unavailable.

6. **Enterprise-Grade Security:**
   - 100% PDO prepared statements for total SQL Injection protection.
   - Bcrypt password hashing (`PASSWORD_BCRYPT`).
   - CSRF protection on all state-modifying requests.
   - XSS sanitization via `htmlspecialchars()`.
   - MIME-type inspection and sanitized filename hashing for all uploads.

---

## 🛠️ Technology Stack

- **Frontend:** HTML5, CSS3, JavaScript (ES6+), Bootstrap 5.3, Bootstrap Icons, Chart.js.
- **Backend:** PHP 8.0+ (Clean layered architecture with PDO).
- **Database:** MySQL 8.0+ / MariaDB 10.4+ (InnoDB, UTF-8 Unicode).
- **Environment:** XAMPP (Apache, MySQL, PHP, phpMyAdmin).
- **Notifications:** PHPMailer (SMTP) & SMS Gateway (API / Native Mock Mode).

---

## 📂 Project Directory Structure

```
campusfix/
├── app/
│   ├── config/              # Database, mail, and application settings
│   ├── controllers/         # Business logic (Auth, Student, Tech, Admin, Tickets)
│   ├── models/              # Database entities and data access layer
│   ├── middleware/          # Session guard, RBAC, and CSRF verification
│   ├── services/            # UploadService, NotificationService, SmsService
│   └── helpers/             # Security, sanitization, and formatting utilities
├── assets/
│   ├── css/                 # Modern SaaS stylesheets (Navy & Royal Blue theme)
│   ├── js/                  # Form validation, dynamic UI, and Chart.js scripts
│   ├── images/              # System branding and static graphics
│   └── uploads/             # User-submitted photos (Protected from direct execution)
│       ├── tickets/         # Fault proof and resolution evidence
│       └── avatars/         # User profile pictures
├── database/
│   └── campusfix.sql        # 3NF schema, relational constraints & demonstration seeds
├── docs/
│   ├── SRS.md               # IEEE 830 Software Requirements Specification
│   ├── ERD.md               # Entity-Relationship Diagram & Data Dictionary
│   ├── USE_CASE_DIAGRAMS.md # Use Case & Sequence Diagrams
│   ├── PROJECT_PLAN_AND_SPRINTS.md # 8-Week Agile Sprint Plan
│   └── TEAM_COLLABORATION_AND_GIT_WORKFLOW.md # 5-Member Git & Module Guide
├── views/
│   ├── layouts/             # Shared header, navbar, sidebar, footer
│   ├── auth/                # Login, registration, password recovery
│   ├── student/             # Fault reporting wizard, my tickets, tracking
│   ├── technician/          # Work order queue, inspection, resolution modal
│   ├── admin/               # Triage desk, analytics charts, user management
│   └── errors/              # 403, 404, 500 error pages
├── .gitignore
├── index.php                # Front controller / application entry point
└── README.md
```

---

## ⚙️ Installation & Setup Guide (XAMPP)

Follow these step-by-step instructions to set up CampusFix on your local development machine:

### 1. Prerequisites
- [XAMPP](https://www.apachefriends.org/) (Version 8.0 or higher recommended, with PHP 8.2+ and MySQL).
- A modern web browser (Chrome, Edge, Firefox).

### 2. Project Placement
Ensure the project folder is placed directly inside your XAMPP `htdocs` directory:
```
C:\xampp\htdocs\Campusfix-Campus Maintenance and IT Fault rEPORTING & Management System
```

### 3. Start Apache & MySQL
1. Open the **XAMPP Control Panel**.
2. Click **Start** for **Apache**.
3. Click **Start** for **MySQL**.

### 4. Import Database
1. Open your browser and navigate to **phpMyAdmin**: [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
2. Click on the **Import** tab in the top navigation.
3. Click **Choose File** and select the SQL file located in:
   ```
   database/campusfix.sql
   ```
4. Click **Import** (or **Go**) at the bottom of the page.
5. The `campusfix` database will be created with all 11 tables, constraints, indexes, and pre-seeded demonstration data.

*(Alternatively, run from terminal:)*
```bash
mysql -u root -p < "c:/xampp/htdocs/Campusfix-Campus Maintenance and IT Fault rEPORTING & Management System/database/campusfix.sql"
```

### 5. Access the Application
Open your web browser and navigate to:
```
http://localhost/Campusfix-Campus%20Maintenance%20and%20IT%20Fault%20rEPORTING%20&%20Management%20System/
```

---

## 🔑 Demonstration User Accounts

The database comes pre-seeded with sample accounts for all three user roles. You can log in immediately:

| Role | Email Address | Password | Description |
| :--- | :--- | :--- | :--- |
| **System Administrator** | `admin@campusfix.edu` | `Admin@123` | Full access to triage desk, analytics charts, user and catalog management. |
| **IT Field Technician** | `samuel.it@campusfix.edu` | `Tech@123` | Assigned to networking and computer hardware tickets. |
| **Electrical Technician**| `akua.electric@campusfix.edu` | `Tech@123` | Assigned to electrical and lighting tickets. |
| **Plumbing Technician**  | `david.plumber@campusfix.edu` | `Tech@123` | Assigned to washroom and water pipe tickets. |
| **Student / Reporter**   | `emmanuel.stu@campusfix.edu` | `Student@123`| Computer Science student with submitted tickets. |
| **Student / Reporter**   | `grace.stu@campusfix.edu` | `Student@123`| Engineering student with active and reopened tickets. |

---

## 📚 Stage 1 Deliverables Summary

- [x] **Project Requirements Analysis:** Comprehensive synthesis of real-world university maintenance operations.
- [x] **Folder Structure:** Scalable, modular architecture ready for full-stack implementation.
- [x] **3NF Database Schema:** 11 normalized tables with foreign keys, cascading constraints, and performance indexes.
- [x] **Importable SQL Script:** Complete database generation and seed data (`database/campusfix.sql`).
- [x] **Software Requirements Specification (SRS):** IEEE 830-compliant specification (`docs/SRS.md`).
- [x] **Entity-Relationship Diagram (ERD):** Visual Mermaid diagram and data dictionary (`docs/ERD.md`).
- [x] **Use Case & Sequence Diagrams:** Actor interaction maps and message sequences (`docs/USE_CASE_DIAGRAMS.md`).
- [x] **Project Development & 8-Week Sprint Plan:** Agile Scrum breakdown with deliverables and risk matrix (`docs/PROJECT_PLAN_AND_SPRINTS.md`).
- [x] **Team Collaboration & GitHub Guide:** Role allocation for 5 members and Git Flow standards (`docs/TEAM_COLLABORATION_AND_GIT_WORKFLOW.md`).
- [x] **README & Installation Manual:** Complete setup guide with demo credentials (`README.md`).

---

## 👥 Engineering Team
- **Kwame Bonsu & Group Team Members**
- Department of Computer Science / Software Engineering
