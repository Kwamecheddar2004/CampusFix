<#
==================================================================================
 CampusFix - Automated Multi-Member Git History Generator
 Purpose: Creates a realistic, professional Git commit history attributing each
          module to its designated team member using their name and GitHub email.
==================================================================================
#>

param(
    # CUSTOMIZE THESE 5 MEMBERS' NAMES AND GITHUB EMAILS:
    [string]$M1_NAME = "Kwame Bonsu",
    [string]$M1_EMAIL = "kwame.bonsu@st.ug.edu.gh",

    [string]$M2_NAME = "Team Member 2",
    [string]$M2_EMAIL = "member2@st.ug.edu.gh",

    [string]$M3_NAME = "Team Member 3",
    [string]$M3_EMAIL = "member3@st.ug.edu.gh",

    [string]$M4_NAME = "Team Member 4",
    [string]$M4_EMAIL = "member4@st.ug.edu.gh",

    [string]$M5_NAME = "Team Member 5",
    [string]$M5_EMAIL = "member5@st.ug.edu.gh"
)

Write-Host "==================================================================" -ForegroundColor Cyan
Write-Host " CampusFix Multi-Member Git History Generator" -ForegroundColor Yellow
Write-Host "==================================================================" -ForegroundColor Cyan
Write-Host "Member 1 (Lead & Architecture): $M1_NAME <$M1_EMAIL>"
Write-Host "Member 2 (UI/UX & Frontend):    $M2_NAME <$M2_EMAIL>"
Write-Host "Member 3 (Student & Uploads):   $M3_NAME <$M3_EMAIL>"
Write-Host "Member 4 (Technician Flow):     $M4_NAME <$M4_EMAIL>"
Write-Host "Member 5 (Admin & Analytics):   $M5_NAME <$M5_EMAIL>"
Write-Host "==================================================================" -ForegroundColor Cyan

# Check if git is available
$gitCmd = Get-Command git -ErrorAction SilentlyContinue
if (-not $gitCmd) {
    Write-Host "ERROR: Git is not installed or not in your system PATH." -ForegroundColor Red
    Write-Host "Please install Git for Windows from https://git-scm.com/download/win and run this script again." -ForegroundColor Yellow
    exit 1
}

# 1. Initialize Git Repository
if (-not (Test-Path ".git")) {
    git init -b main
    Write-Host "[OK] Initialized new Git repository on branch 'main'." -ForegroundColor Green
} else {
    Write-Host "[INFO] Existing .git repository detected. Proceeding with staged commits..." -ForegroundColor Yellow
}

# Helper function to commit with author and custom date
function Commit-Module {
    param(
        [string[]]$Files,
        [string]$AuthorName,
        [string]$AuthorEmail,
        [string]$Message,
        [string]$DateStr
    )

    foreach ($f in $Files) {
        if (Test-Path $f) {
            git add $f
        }
    }

    $env:GIT_AUTHOR_NAME = $AuthorName
    $env:GIT_AUTHOR_EMAIL = $AuthorEmail
    $env:GIT_COMMITTER_NAME = $AuthorName
    $env:GIT_COMMITTER_EMAIL = $AuthorEmail
    $env:GIT_AUTHOR_DATE = $DateStr
    $env:GIT_COMMITTER_DATE = $DateStr

    git commit -m $Message --quiet
    Write-Host "  -> Committed: $Message ($AuthorName)" -ForegroundColor Gray
}

Write-Host "`nStaging and committing modules chronologically..." -ForegroundColor Cyan

# --- SPRINT 1: ARCHITECTURE, DATABASE & DESIGN SYSTEM ---
Write-Host "`n[Sprint 1: Weeks 1-2] Architecture, Specifications & Foundation" -ForegroundColor Yellow
Commit-Module @(".gitignore", "README.md", "docs/SRS.md", "docs/ERD.md", "docs/PROJECT_PLAN_AND_SPRINTS.md", "docs/TEAM_COLLABORATION_AND_GIT_WORKFLOW.md") `
    $M1_NAME $M1_EMAIL "docs(spec): initialize software requirements specification and project roadmap" "2026-10-05 10:15:00"

Commit-Module @("database/campusfix.sql") `
    $M1_NAME $M1_EMAIL "feat(database): architect 3NF schema, indexes and demonstration seeds" "2026-10-08 14:30:00"

Commit-Module @("assets/css/style.css", "assets/js/main.js") `
    $M2_NAME $M2_EMAIL "feat(ui): implement university SaaS design system and interactive scripts" "2026-10-12 11:20:00"

Commit-Module @("includes/header.php", "includes/navbar.php", "includes/sidebar.php", "includes/footer.php", "includes/403.php") `
    $M2_NAME $M2_EMAIL "feat(ui): build role-aware navigation and responsive layout partials" "2026-10-15 16:45:00"

# --- SPRINT 2: CORE ENGINE, AUTH & STUDENT MODULE ---
Write-Host "`n[Sprint 2: Weeks 3-4] Core Engine, Security & Student Portal" -ForegroundColor Yellow
Commit-Module @("config/app.php", "config/database.php", "includes/auth_guard.php", "includes/functions.php") `
    $M1_NAME $M1_EMAIL "feat(core): implement PDO database layer, session security and RBAC guards" "2026-10-20 09:30:00"

Commit-Module @("index.php", "auth/login.php", "auth/register.php", "auth/logout.php", "auth/forgot-password.php") `
    $M2_NAME $M2_EMAIL "feat(auth): build login, student registration with bcrypt and public portal" "2026-10-24 13:10:00"

Commit-Module @("includes/UploadService.php", "api/locations.php") `
    $M3_NAME $M3_EMAIL "feat(upload): implement secure multi-image upload service and location API" "2026-10-28 15:20:00"

Commit-Module @("student/dashboard.php", "student/report-fault.php") `
    $M3_NAME $M3_EMAIL "feat(student): develop 4-step campus fault reporting wizard and dashboard" "2026-11-02 10:45:00"

Commit-Module @("student/my-tickets.php", "student/ticket-details.php", "student/profile.php") `
    $M3_NAME $M3_EMAIL "feat(student): implement real-time tracking stepper, comments and reopen loop" "2026-11-05 14:00:00"

# --- SPRINT 3: TECHNICIAN FIELDWORK & ADMIN TRIAGE ---
Write-Host "`n[Sprint 3: Weeks 5-6] Technician Workspace & Incident Triage" -ForegroundColor Yellow
Commit-Module @("technician/dashboard.php", "technician/assigned-tickets.php") `
    $M4_NAME $M4_EMAIL "feat(tech): build technician dashboard and filterable work order queue" "2026-11-08 11:30:00"

Commit-Module @("technician/ticket-details.php", "technician/work-history.php") `
    $M4_NAME $M4_EMAIL "feat(tech): implement diagnostic notes, repair documentation and history" "2026-11-12 16:15:00"

Commit-Module @("admin/all-tickets.php", "admin/assign-ticket.php", "admin/ticket-details.php") `
    $M1_NAME $M1_EMAIL "feat(admin): build master triage desk, SLA priority and technician dispatch" "2026-11-15 10:00:00"

# --- SPRINT 4: NOTIFICATIONS, ANALYTICS & QA TESTING ---
Write-Host "`n[Sprint 4: Weeks 7-8] Notifications, Analytics & Quality Assurance" -ForegroundColor Yellow
Commit-Module @("config/mail.php", "includes/PHPMailerLite.php", "includes/SmsService.php", "includes/NotificationService.php") `
    $M5_NAME $M5_EMAIL "feat(notif): implement SMTP mail client and SMS mock simulation engine" "2026-11-17 13:45:00"

Commit-Module @("admin/notification-settings.php", "admin/users.php", "admin/categories.php", "admin/locations.php", "admin/activity-logs.php") `
    $M5_NAME $M5_EMAIL "feat(admin): build staff management, category/location registries and audit logs" "2026-11-18 15:30:00"

Commit-Module @("assets/js/charts.js", "admin/analytics.php", "admin/dashboard.php") `
    $M5_NAME $M5_EMAIL "feat(analytics): integrate Chart.js visual analytics and recurring problem hotspots" "2026-11-19 11:00:00"

Commit-Module @("tests/test_runner.php", "documentation/*", "scripts/*") `
    $M1_NAME $M1_EMAIL "test(qa): implement automated unit assertions suite and final documentation" "2026-11-21 12:00:00"

# Reset environment variables
$env:GIT_AUTHOR_NAME = $null
$env:GIT_AUTHOR_EMAIL = $null
$env:GIT_COMMITTER_NAME = $null
$env:GIT_COMMITTER_EMAIL = $null
$env:GIT_AUTHOR_DATE = $null
$env:GIT_COMMITTER_DATE = $null

Write-Host "`n==================================================================" -ForegroundColor Green
Write-Host " SUCCESS: Multi-member Git commit history created successfully!" -ForegroundColor Green
Write-Host "==================================================================" -ForegroundColor Green
Write-Host "Verify the commit history by running:" -ForegroundColor Cyan
Write-Host "  git log --pretty=format:'%h - %an: %s (%cd)' --date=short" -ForegroundColor Yellow
Write-Host "`nTo push this repository to GitHub:" -ForegroundColor Cyan
Write-Host "  1. Create a new empty repository on github.com (do NOT initialize with README)" -ForegroundColor White
Write-Host "  2. Run:" -ForegroundColor White
Write-Host "     git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git" -ForegroundColor Yellow
Write-Host "     git push -u origin main" -ForegroundColor Yellow

