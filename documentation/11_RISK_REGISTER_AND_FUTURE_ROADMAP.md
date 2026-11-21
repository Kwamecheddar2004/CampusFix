# Risk Register & Strategic Future Enhancements
## CampusFix – Campus Maintenance and IT Fault Reporting & Management System

**Document Version:** 1.0.0  
**Status:** Approved for Implementation  

---

## 1. Project Risk Register

The following matrix documents operational, security, and technical risks identified during development and deployment, alongside implemented mitigations and contingency plans.

| Risk ID | Risk Description | Category | Prob. | Impact | Score | Implemented Mitigation Strategy | Contingency Plan |
| :--- | :--- | :--- | :---: | :---: | :---: | :--- | :--- |
| **RSK-01** | **XAMPP Local MySQL Port Conflict (Port 3306 in use)** | Infrastructure | Med | High | **12** | Database wrapper checks connection gracefully and renders a friendly setup instruction screen rather than an unhandled crash. | Change MySQL port to 3307 in `my.ini` and update `config/database.php`. |
| **RSK-02** | **Malicious File Upload (Web Shell / PHP Script Upload)** | Security | Med | High | **12** | Enforced binary MIME sniffing via PHP `finfo`, strict file extension whitelisting (`jpg, png, webp`), 5MB size limit, and randomized file renaming. | Disable PHP script execution in `uploads/` via Apache `.htaccess` directive. |
| **RSK-03** | **Third-Party SMTP / SMS Gateway Downtime** | Integration | High | Med | **12** | Implemented transparent **Mock Simulation Mode** for both email and SMS that logs payloads to `logs/` and marks database records as `mock_delivered`. | Switch between live gateway and mock mode via one-click toggle in `/admin/notification-settings.php`. |
| **RSK-04** | **Privilege Escalation (Student accessing Admin/Tech routes)** | Security | Low | High | **8** | Built strict RBAC middleware (`require_role()`) invoked on line 10 of every portal page; unauthorized roles receive HTTP 403 Forbidden. | Centralized session termination and security logging in `activity_logs`. |
| **RSK-05** | **Cross-Site Request Forgery (CSRF) on Form POSTs** | Security | Med | Med | **9** | Embedded random per-session 64-char CSRF tokens in all forms verified by `enforce_csrf()`. | Reject requests with HTTP 403 on token mismatch. |
| **RSK-06** | **Cross-Site Scripting (XSS) via Fault Descriptions** | Security | Med | Med | **9** | Passed all dynamic database variables through `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`. | Strict HTML entity encoding on all view templates. |

---

## 2. Strategic Future Enhancements (Post-Capstone Roadmap)

While CampusFix satisfies all capstone requirements as a fully functional web platform, the following features are planned for future iterations:

### 1. Equipment QR Code Tagging
- Affix weatherproof QR code stickers to physical campus equipment (projectors, lab PCs, air conditioners, washroom cisterns).
- Scanning the QR code with a smartphone camera opens CampusFix with the Building, Floor, Room, and Hardware Model pre-filled automatically.

### 2. Interactive SVG Campus Map & Heatmap
- Visual 2D architectural map of the campus.
- Buildings dynamically colored green (0 faults), yellow (1-2 faults), or red (3+ open faults or critical hazard) for physical plant managers.

### 3. University Single Sign-On (SSO / LDAP / Google Workspace)
- Direct integration with university identity providers (Microsoft Azure Active Directory / Google Workspace for Education / Shibboleth).
- Eliminates separate student registration by authenticating directly through university student credentials.

### 4. Progressive Web App (PWA) & Native Push Notifications
- Service Worker caching enabling offline report drafting when students have zero Wi-Fi connectivity.
- Web Push notifications delivering push alerts directly to technician smartphone lock screens upon job assignment.

### 5. Spare Parts Inventory & Procurement Ledger
- Basic ERP stock tracking deducting replacement capacitors, switches, Cat6 patch cables, and plumbing valves as technicians mark jobs resolved.
- Automated reorder alerts when inventory drops below safety thresholds.

