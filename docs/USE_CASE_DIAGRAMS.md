# Use Case Diagrams & Behavioral Specifications
## CampusFix – Campus Maintenance and IT Fault Reporting & Management System

**Document Version:** 1.0.0  
**Status:** Approved for Implementation (Stage 1)  

---

## 1. System Actors

| Actor | Type | Description |
| :--- | :--- | :--- |
| **Student / Campus Reporter** | Human (Primary) | University student, faculty member, or staff reporting maintenance or IT breakdowns. |
| **Field Technician** | Human (Primary) | University maintenance artisan or IT engineer assigned to diagnose and resolve faults. |
| **System Administrator** | Human (Primary) | Facilities supervisor or IT helpdesk manager with full operational oversight and dispatch privileges. |
| **Notification Engine** | Automated (Secondary) | Background service dispatching in-app alerts, SMTP emails, and SMS messages. |

---

## 2. High-Level System Use Case Diagram

```mermaid
flowchart LR
    subgraph Actors
        Student["fa:fa-user Student / Reporter"]
        Tech["fa:fa-tools Field Technician"]
        Admin["fa:fa-user-shield System Admin"]
        NotifSystem["fa:fa-bell Notification Engine"]
    end

    subgraph Authentication_Module ["Authentication & Profile"]
        UC_Register("Register Student Account")
        UC_Login("Login & Multi-Role Session")
        UC_Profile("Manage Profile & Password")
    end

    subgraph Student_Module ["Student Self-Service"]
        UC_Report("Submit Fault Report with Photos")
        UC_Track("Track Ticket Status & History")
        UC_Comment("Post Public Comment / Query")
        UC_Confirm("Confirm Resolution & Rate Service")
        UC_Reopen("Reopen Incomplete Ticket")
    end

    subgraph Technician_Module ["Technician Fieldwork"]
        UC_ViewJobs("View Assigned Jobs & Filter")
        UC_Inspect("Inspect Fault Photos & Details")
        UC_StartWork("Accept & Start Work (In Progress)")
        UC_Resolve("Document Repair & Mark Resolved")
        UC_InternalNotes("Add Private Technical Notes")
    end

    subgraph Admin_Module ["Administrative Control"]
        UC_Triage("Triage & Assign Technicians")
        UC_SetPriority("Set Priority & SLA Deadlines")
        UC_ManageUsers("Manage Users & Roles")
        UC_ManageCatalog("Manage Categories & Locations")
        UC_Analytics("View KPI Metrics & Recurring Issues")
        UC_AuditLogs("Audit System Activity Logs")
    end

    %% Actor Connections
    Student --> UC_Register
    Student --> UC_Login
    Student --> UC_Profile
    Student --> UC_Report
    Student --> UC_Track
    Student --> UC_Comment
    Student --> UC_Confirm
    Student --> UC_Reopen

    Tech --> UC_Login
    Tech --> UC_Profile
    Tech --> UC_ViewJobs
    Tech --> UC_Inspect
    Tech --> UC_StartWork
    Tech --> UC_Resolve
    Tech --> UC_InternalNotes

    Admin --> UC_Login
    Admin --> UC_Profile
    Admin --> UC_Triage
    Admin --> UC_SetPriority
    Admin --> UC_ManageUsers
    Admin --> UC_ManageCatalog
    Admin --> UC_Analytics
    Admin --> UC_AuditLogs

    %% System Actor Connections
    UC_Report -.-> NotifSystem
    UC_Triage -.-> NotifSystem
    UC_Resolve -.-> NotifSystem
    UC_Reopen -.-> NotifSystem
```

---

## 3. Core Behavioral Sequence Diagrams

### 3.1 Sequence 1: Fault Submission & Automatic Triage Notification
```mermaid
sequenceDiagram
    autonumber
    actor Reporter as Student / Reporter
    participant WebUI as Browser UI (Bootstrap 5)
    participant Controller as TicketController (PHP)
    participant Upload as UploadService
    participant DB as MySQL Database
    participant Notif as NotificationService

    Reporter->>WebUI: Fill fault form (Location, Category, Severity, Title, Photos)
    WebUI->>Controller: POST /tickets/create (with CSRF token & multipart files)
    Controller->>Controller: Validate inputs & sanitize text
    Controller->>Upload: Process & validate images (MIME, 5MB limit)
    Upload-->>Controller: Return sanitized file storage paths
    Controller->>DB: INSERT INTO tickets (status='pending', ticket_number='CF-2026-XXXX')
    Controller->>DB: INSERT INTO ticket_images (file_path, ticket_id)
    Controller->>DB: INSERT INTO ticket_status_history (new_status='pending')
    Controller->>Notif: Trigger Event: TICKET_CREATED
    Notif->>DB: Queue admin in-app notification & log mock SMS/email
    Controller-->>WebUI: Redirect with success toast & Ticket ID
    WebUI-->>Reporter: Display confirmation and live tracking view
```

### 3.2 Sequence 2: Administrative Triage & Technician Dispatch
```mermaid
sequenceDiagram
    autonumber
    actor Admin as System Administrator
    participant WebUI as Admin Portal
    participant Controller as AdminController (PHP)
    participant DB as MySQL Database
    participant Notif as NotificationService
    actor Tech as Field Technician

    Admin->>WebUI: View Pending tickets table
    Admin->>WebUI: Select ticket, assign Technician & set Priority to High
    WebUI->>Controller: POST /admin/tickets/assign
    Controller->>DB: UPDATE tickets SET assigned_to=?, priority=?, status='assigned'
    Controller->>DB: INSERT INTO ticket_assignments (ticket_id, technician_id)
    Controller->>DB: INSERT INTO ticket_status_history (old='pending', new='assigned')
    Controller->>Notif: Dispatch Assignment Alerts
    Notif->>Tech: In-App Alert + Email / SMS ("New Job CF-2026-0003 Assigned")
    Notif->>DB: Log notification status
    Controller-->>WebUI: JSON response: Assignment successful
    WebUI-->>Admin: Update dashboard KPI counters
```

### 3.3 Sequence 3: Repair Execution, Resolution & Student Feedback Loop
```mermaid
sequenceDiagram
    autonumber
    actor Tech as Field Technician
    participant TechUI as Technician Portal
    participant Controller as TechnicianController
    participant DB as MySQL Database
    actor Student as Student / Reporter

    Tech->>TechUI: Open assigned ticket & click "Start Work"
    TechUI->>Controller: POST /technician/ticket/status ('in_progress')
    Controller->>DB: UPDATE tickets SET status='in_progress'

    Note over Tech: Technician diagnoses and completes repair work

    Tech->>TechUI: Click "Mark Resolved", enter repair actions & notes
    TechUI->>Controller: POST /technician/ticket/resolve (resolution_notes)
    Controller->>DB: UPDATE tickets SET status='resolved', resolved_at=NOW()
    Controller->>DB: INSERT INTO ticket_status_history ('in_progress' -> 'resolved')
    Controller-->>Student: Alert: "Fault CF-2026-0002 has been marked Resolved"

    alt Student Satisfied
        Student->>TechUI: Click "Confirm Resolution", rate 5 stars & leave feedback
        Controller->>DB: UPDATE tickets SET status='closed', satisfaction_rating=5
    else Fault Still Exists
        Student->>TechUI: Click "Reopen Ticket", enter failure reason
        Controller->>DB: UPDATE tickets SET status='reopened'
        Controller->>DB: INSERT INTO ticket_status_history ('resolved' -> 'reopened')
    end
```

---

## 4. Granular Use Case Narratives

### UC-01: Submit Fault Report
- **Primary Actor:** Student / Campus Reporter
- **Preconditions:** User must be logged in with an active account.
- **Trigger:** User navigates to `/student/report-fault`.
- **Main Flow:**
  1. System displays category options, campus location dropdowns (Building, Floor, Room), severity radio buttons, title, and description fields.
  2. User selects Category (e.g., "Electrical & Power Outlets").
  3. User selects Location (e.g., "Balme Central Library > Ground Floor > Reading Hall A").
  4. User enters Title: "Sparks from power outlet on column 4".
  5. User attaches 2 JPG photos of the damaged outlet.
  6. User clicks "Submit Report".
  7. System validates fields, inspects image MIME types, creates ticket ID `CF-2026-0003`, saves records, and dispatches admin alerts.
  8. System redirects user to the ticket tracking view with a success notification.
- **Alternate Flows:**
  - *Image exceeds 5MB or invalid format:* System displays inline validation warning and preserves form input.
- **Postconditions:** Ticket is recorded in the database with status `pending`.

### UC-02: Document Repair & Mark Resolved
- **Primary Actor:** Field Technician
- **Preconditions:** Technician is authenticated; ticket is currently assigned to them.
- **Trigger:** Technician selects "Mark Resolved" modal from their workspace.
- **Main Flow:**
  1. Technician opens the ticket detail modal.
  2. Technician enters technical resolution notes (e.g., "Replaced burned socket base with 13A dual switched unit; tested voltage at 230V").
  3. Technician optionally uploads an after-fix photo.
  4. Technician submits resolution form.
  5. System records `resolved_at` timestamp, changes ticket state to `resolved`, updates `ticket_status_history`, and alerts the student reporter.
- **Postconditions:** Ticket enters `resolved` state awaiting user closure or reopening.

### UC-03: View Analytics & Recurring Issues
- **Primary Actor:** System Administrator
- **Preconditions:** Admin is logged in.
- **Main Flow:**
  1. Admin opens `/admin/analytics`.
  2. System queries aggregate tables:
     - Top fault categories (Chart.js doughnut chart).
     - Fault frequency by campus building (Chart.js bar chart).
     - Average turnaround time (MTTR in hours).
     - Technician workload distribution (Assigned vs Completed).
     - Overdue SLA incidents.
  3. Admin filters analytics by date range (Past 7 days, 30 days, or Semester).
- **Postconditions:** Facilities management gains clear operational visibility for preventative maintenance planning.
