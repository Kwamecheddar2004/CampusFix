# CampusFix User Manual & Operational Guide
## Comprehensive Guide for Students, Technicians, and Administrators

**Document Version:** 1.0.0  
**Target Audience:** University Campus Community & Technical Evaluation Committee  

---

## 1. Accessing the CampusFix Portal
- **Local Development URL:** `http://localhost/Campusfix-Campus%20Maintenance%20and%20IT%20Fault%20rEPORTING%20&%20Management%20System/`
- **Supported Browsers:** Google Chrome, Mozilla Firefox, Microsoft Edge, Safari.
- **Mobile Support:** Fully responsive; open the browser on any connected campus smartphone or tablet.

---

## 2. Student / Reporter User Guide

### 2.1 Student Registration & Login
1. On the homepage, click **"Student Sign Up"** or **"Register Student Account"**.
2. Enter your Full Name, University Email (`student@st.ug.edu.gh`), Student ID, Academic Department, Phone Number, and Password (minimum 8 characters).
3. Click **"Register Student Account"**. You will be logged in immediately and redirected to the Student Dashboard.
4. For returning students: click **"Sign In"**, enter your email and password, and click **"Sign In"**.

### 2.2 Submitting a Fault Report
1. From the top navigation bar or sidebar, click **"Report Problem"** or **"+ Report Fault"**.
2. **Step 1 (Classification & Location):**
   - Select the **Fault Category** (e.g., *Wi-Fi & Internet Connectivity*, *Projectors & AV*, *Electrical & Power*).
   - Select the **Campus Location / Room** (e.g., *Balme Central Library — Ground Floor (Reading Hall A)*).
   - Optionally enter **Pinpoint Area Details** (e.g., *3rd study cubicle from window, socket #4*).
3. **Step 2 (Problem Details):**
   - Enter a descriptive **Summary Title** (e.g., *Cisco Wi-Fi access point dropping client connections*).
   - Provide a **Detailed Description** explaining failure symptoms, error codes, and physical observations.
4. **Step 3 (Severity Level):**
   - Choose from: `Low`, `Medium`, `High`, or `Critical Hazard` (red indicator).
5. **Step 4 (Attach Photos):**
   - Click **"Choose Photos"** and select up to 3 JPG, PNG, or WEBP images. Live thumbnails will display immediately.
6. Click **"Submit Fault Report"**.
7. The system generates your unique tracking number (`CF-2026-XXXX`), saves your report, and automatically routes you to the tracking view.

### 2.3 Tracking Ticket Progress
1. Click **"My Reports"** in the sidebar to view all your reported incidents.
2. Use the search bar or status filter tabs (`Pending`, `In Progress`, `Resolved`, `Closed`).
3. Click **"Track"** on any ticket to view:
   - **Status Stepper:** Visual pipeline displaying current phase (`Pending` ➔ `Assigned` ➔ `In Progress` ➔ `Resolved` ➔ `Closed`).
   - **Assigned Technician Details:** Technician's name, email, and campus phone extension.
   - **Photographic Gallery:** Click photos to inspect full-size images.
   - **Status History Audit:** Chronological log of every change made to your ticket.
   - **Comments:** Post questions or updates to the technician using the message box.

### 2.4 Confirming Resolution or Reopening
- When a technician marks your issue fixed, the ticket status changes to `Resolved`, and you receive an alert.
- **If the problem is fixed:** Click **"Confirm Fix & Close"**, select a service quality rating ($1-5\bigstar$), leave feedback, and close the ticket.
- **If the fault persists:** Click **"Reopen Ticket"**, describe what is still broken, and the ticket will be returned to the technician dispatch queue immediately.

---

## 3. Field Technician User Guide

### 3.1 Technician Login & Work Dashboard
1. Navigate to `/auth/login.php`.
2. For testing, click the quick-login pill: **"🔧 IT Tech"** or **"⚡ Electrician"**, or enter your staff email and password (`Tech@123`).
3. Your **Technician Dashboard** displays:
   - Total assigned work orders.
   - Jobs ready to start.
   - Currently in-progress repairs.
   - Urgent SLA incidents.

### 3.2 Managing Assigned Jobs
1. Click **"Assigned Jobs"** in the sidebar.
2. Filter jobs by status (`New`, `In Progress`, `Reopened`) or priority (`Urgent`, `High`, `Medium`).
3. Click **"Update"** on any work order to view:
   - Full student description, reported severity, and attached fault photos.
   - Reporter's contact phone number and academic department.
   - Administrative dispatch directives.

### 3.3 Starting Work & Documenting Repairs
1. When arriving on-site or commencing diagnostics, click **"Start Work"**. The status updates to `In Progress`, and the student is notified.
2. **Adding Private Investigation Notes:** Write technical observations, check the box **"Internal staff note"**, and click **"Save Note"**. These notes are visible only to you and administrators.
3. **Marking Resolved:**
   - Click **"Mark as Resolved"**.
   - Enter **Repair Actions Taken** (e.g., *Replaced burnt 13A socket plate; verified voltage at 230V; load tested*).
   - Enter **Replacement Parts Used** (e.g., *MK 13A switched dual socket base*).
   - Optionally attach an after-repair proof photo.
   - Click **"Submit Resolution"**. The student is alerted to test and verify the fix.

### 3.4 Reviewing Completed History
- Click **"Work History"** in the sidebar to view all resolved repairs, student star ratings received, and your lifetime resolution metrics.

---

## 4. System Administrator User Guide

### 4.1 Master Triage Desk & Technician Dispatch
1. Log in with `admin@campusfix.edu` / `Admin@123` (or click the **"🛡️ Admin"** quick-fill pill).
2. Go to **"Master Triage Desk"** (`/admin/all-tickets.php`).
3. Tickets requiring triage appear with status `Pending`.
4. Click **"Assign Tech"** or the gear icon:
   - Select a technician from the roster (each technician card shows their current active workload).
   - Set the administrative priority (`Urgent: 4h SLA`, `High: 12h`, `Medium: 24h`, `Low: 72h`).
   - Enter specific dispatch instructions.
   - Click **"Dispatch Ticket Now"**. Both technician and student receive notifications.

### 4.2 Facility Analytics & Recurring Fault Intelligence
1. Click **"Analytics & Reports"** (`/admin/analytics.php`).
2. Review real database KPIs:
   - **Resolution Rate (%)** and **Mean Time to Resolution (MTTR in hours)**.
   - **Average Student Satisfaction Rating ($1-5\bigstar$)**.
   - **Overdue Incidents:** Count of tickets exceeding priority SLA windows.
   - **Category Distribution:** Interactive Chart.js doughnut chart.
   - **Campus Building Trouble Spots:** Chart.js bar chart identifying problem structures.
   - **Technician Workload Roster:** Compares active vs. completed repairs per artisan.
   - **Recurring Problem Hotspots Table:** Automatically flags locations with recurring hardware failures for preventative equipment replacement.

### 4.3 Catalog, User & Notification Administration
- **User Management (`/admin/users.php`):** View students and staff, toggle account activation/suspension, or register new technicians.
- **Fault Categories (`/admin/categories.php`):** Add new issue categories, define department responsibilities, assign Bootstrap icons, and customize UI badge colors.
- **Campus Locations (`/admin/locations.php`):** Add campus buildings, floors, and rooms.
- **Notification Settings (`/admin/notification-settings.php`):** Configure SMTP host/port/credentials, toggle Email & SMS Mock Modes, test live/simulated message dispatch, and view the notification delivery audit ledger.
- **Activity Audit Logs (`/admin/activity-logs.php`):** Filter and review system compliance logs, client IP addresses, user agents, and serialized event payloads.

