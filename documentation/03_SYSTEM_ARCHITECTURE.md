# System Architecture & Technical Diagrams
## CampusFix – Campus Maintenance and IT Fault Reporting & Management System

**Document Version:** 1.0.0  
**Status:** Approved for Implementation (Stage 1)  

---

## 1. Multi-Tier Application Architecture

CampusFix utilizes a modern, modular 3-Tier Web Application Architecture designed for maintainability, security, and clean separation of concerns.

```
+-------------------------------------------------------------------------+
|                         PRESENTATION TIER (CLIENT)                      |
|  - HTML5 / CSS3 / JavaScript (ES6+)                                     |
|  - Bootstrap 5.3 Framework & Responsive Grid                            |
|  - Chart.js Analytics Visualizations                                    |
|  - Bootstrap Icons & SaaS UI Design Tokens                              |
+-------------------------------------------------------------------------+
                                    |
                                    | HTTPS / HTTP (RESTful & Form POST)
                                    v
+-------------------------------------------------------------------------+
|                         APPLICATION TIER (SERVER)                       |
|  - Web Server: Apache 2.4 (VirtualHost / mod_rewrite)                   |
|  - Runtime: PHP 8.0+                                                    |
|  - Routing & Middleware: AuthMiddleware, CsrfMiddleware, RBAC Guard     |
|  - Controllers: Auth, Student, Technician, Admin, Tickets               |
|  - Services: NotificationService, UploadService, SmsService             |
|  - Helpers: Security, Formatting, Mailer (PHPMailer SMTP)               |
+-------------------------------------------------------------------------+
                                    |
                                    | PDO (Prepared Statements & Pool)
                                    v
+-------------------------------------------------------------------------+
|                            DATA TIER (STORAGE)                          |
|  - RDBMS: MySQL 8.0+ / MariaDB 10.4+ (InnoDB Engine)                    |
|  - 11 Normalized Tables in 3NF (Transactions, Audit Logs, Settings)    |
|  - File System: Secure Uploads Repository (/uploads/tickets, /avatars)  |
+-------------------------------------------------------------------------+
```

---

## 2. Object-Oriented Class Diagram

The following Mermaid class diagram illustrates the primary entity models, controllers, and service providers powering CampusFix.

```mermaid
classDiagram
    class User {
        +int id
        +string user_code
        +string full_name
        +string email
        +string phone
        +string password_hash
        +string role
        +string department
        +string status
        +authenticate(email, password) bool
        +register(data) int
        +updateProfile(id, data) bool
    }

    class Ticket {
        +int id
        +string ticket_number
        +int reporter_id
        +int category_id
        +int location_id
        +string location_details
        +string title
        +text description
        +string severity
        +string priority
        +string status
        +int assigned_to
        +createTicket(data) string
        +assignTechnician(ticket_id, tech_id, admin_id) bool
        +updateStatus(ticket_id, new_status, user_id, notes) bool
        +resolve(ticket_id, notes, tech_id) bool
        +reopen(ticket_id, reason, student_id) bool
    }

    class Category {
        +int id
        +string name
        +string slug
        +string department_in_charge
        +string icon
        +string color_code
        +getAllActive() array
        +save(data) bool
    }

    class CampusLocation {
        +int id
        +string building_name
        +string building_code
        +string floor
        +string room_number
        +getAllLocations() array
        +getByBuilding(building_code) array
    }

    class NotificationService {
        +dispatch(user_id, ticket_id, title, message, channel) bool
        +sendEmail(to, subject, body) bool
        +sendSms(to, message) bool
        +logMockNotification(data) bool
    }

    class UploadService {
        +uploadImages(files, ticket_id, user_id) array
        +validateFile(file) bool
        +sanitizeFilename(filename) string
    }

    class AnalyticsService {
        +getDashboardMetrics() array
        +getCategoryBreakdown() array
        +getBuildingHotspots() array
        +getTechnicianWorkloads() array
        +getMeanTimeToResolution() float
    }

    User "1" <-- "0..*" Ticket : reports
    User "1" <-- "0..*" Ticket : assigned_to
    Category "1" <-- "0..*" Ticket : classifies
    CampusLocation "1" <-- "0..*" Ticket : locates
    Ticket "1" *-- "0..*" NotificationService : triggers
    Ticket "1" *-- "0..*" UploadService : contains_images
    AnalyticsService ..> Ticket : queries
```

---

## 3. System Activity Diagrams

### 3.1 Student Fault Reporting Activity
```mermaid
stateDiagram-v2
    [*] --> NavigateToReportForm
    NavigateToReportForm --> FillLocationAndCategory
    FillLocationAndCategory --> EnterTitleAndDescription
    EnterTitleAndDescription --> AttachPhotos
    AttachPhotos --> SubmitReportForm
    
    state ValidationCheck <<choice>>
    SubmitReportForm --> ValidationCheck
    
    ValidationCheck --> DisplayInlineErrors : Invalid Input or File > 5MB
    DisplayInlineErrors --> EnterTitleAndDescription
    
    ValidationCheck --> PersistTicketInDatabase : Form Valid & Files Safe
    PersistTicketInDatabase --> GenerateTicketNumber : CF-2026-XXXX
    GenerateTicketNumber --> DispatchAdminNotification
    DispatchAdminNotification --> DisplaySuccessView
    DisplaySuccessView --> [*]
```

### 3.2 Ticket Triage & Resolution Activity
```mermaid
stateDiagram-v2
    [*] --> Pending : New Report Created
    
    Pending --> Assigned : Admin Assigns Technician
    Assigned --> In_Progress : Technician Commences Work
    
    In_Progress --> Resolved : Technician Submits Resolution Notes
    
    state StudentVerification <<choice>>
    Resolved --> StudentVerification : Reporter Inspects Repair
    
    StudentVerification --> Closed : Satisfied (1-5 Star Rating)
    StudentVerification --> Reopened : Fault Persists (Within 7 Days)
    
    Reopened --> Assigned : Admin Reassigns / Escalates
    Closed --> [*]
```

