<?php
/**
 * CampusFix - Public Landing Portal
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/functions.php';

$user = current_user();

// Fetch live statistics if DB is connected
$totalTickets = 0;
$resolvedTickets = 0;
$activeCategories = [];

try {
    $pdo = get_db_connection();
    $totalTickets = (int) $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
    $resolvedTickets = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('resolved', 'closed')")->fetchColumn();
    $activeCategories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 LIMIT 6")->fetchAll();
} catch (Exception $e) {
    // Graceful fallback for initial preview
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusFix | Campus Maintenance & IT Fault Management System</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/style.css">

    <style>
        .hero-section {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            padding: 5rem 0 4rem 0;
            position: relative;
            overflow: hidden;
        }
        .hero-badge {
            background: rgba(37, 99, 235, 0.2);
            border: 1px solid rgba(37, 99, 235, 0.4);
            color: #93c5fd;
            padding: 0.35rem 0.85rem;
            border-radius: 50rem;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .feature-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.75rem;
            transition: all 0.25s ease;
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.08);
            border-color: #cbd5e1;
        }
        .category-pill {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.25rem;
            text-align: center;
            transition: all 0.2s ease;
        }
        .category-pill:hover {
            border-color: #2563eb;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.08);
        }
    </style>
</head>
<body>

    <!-- Public Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3" style="background-color: #0f172a !important;">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= BASE_URL ?>">
                <span class="bg-primary text-white p-1 rounded-2 d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                    <i class="bi bi-tools"></i>
                </span>
                <span>CampusFix</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item"><a class="nav-link active" href="<?= BASE_URL ?>">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#categories">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="#how-it-works">How It Works</a></li>
                </ul>
                
                <div class="d-flex align-items-center gap-2">
                    <?php if ($user): ?>
                        <span class="text-light me-2 small">Welcome, <strong><?= htmlspecialchars($user['full_name']) ?></strong></span>
                        <a href="<?= BASE_URL ?><?= $user['role'] ?>/dashboard.php" class="btn btn-primary px-3 fw-semibold">
                            <i class="bi bi-speedometer2 me-1"></i> Go to Dashboard
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline-light px-3 fw-medium">Sign In</a>
                        <a href="<?= BASE_URL ?>auth/register.php" class="btn btn-primary px-3 fw-semibold">Student Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section text-center text-lg-start">
        <div class="container py-4">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <div class="hero-badge">
                        <i class="bi bi-patch-check-fill text-primary"></i> Official Campus Maintenance & IT Helpdesk
                    </div>
                    <h1 class="display-5 fw-extrabold text-white mb-3 tracking-tight">
                        Report campus problems.<br>
                        <span class="text-primary" style="color: #60a5fa !important;">Track repairs in real-time.</span>
                    </h1>
                    <p class="lead text-light opacity-75 mb-4" style="max-width: 580px;">
                        Experiencing failing Wi-Fi, broken lab computers, burnt projector lamps, or washroom plumbing issues? CampusFix delivers your complaint directly to certified university technicians.
                    </p>
                    <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                        <?php if ($user): ?>
                            <a href="<?= BASE_URL ?>student/report-fault.php" class="btn btn-primary btn-lg px-4 fw-semibold shadow">
                                <i class="bi bi-plus-circle me-1"></i> Report a Fault Now
                            </a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>auth/register.php" class="btn btn-primary btn-lg px-4 fw-semibold shadow">
                                <i class="bi bi-pencil-square me-1"></i> Register Student Account
                            </a>
                            <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline-light btn-lg px-4 fw-semibold">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Staff / Tech Portal
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="p-4 rounded-4 shadow-lg text-dark" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);">
                        <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-activity text-primary"></i> Real-Time Service Status
                        </h5>
                        <div class="row g-3 text-center mb-3">
                            <div class="col-6">
                                <div class="p-3 bg-light rounded-3 border">
                                    <div class="fs-3 fw-bold text-primary"><?= $totalTickets ?></div>
                                    <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.75rem;">Total Incidents</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 bg-light rounded-3 border">
                                    <div class="fs-3 fw-bold text-success"><?= $resolvedTickets ?></div>
                                    <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.75rem;">Repairs Resolved</small>
                                </div>
                            </div>
                        </div>
                        <div class="p-3 rounded-3 bg-primary-subtle text-primary border border-primary-subtle small">
                            <i class="bi bi-shield-check me-1 fw-bold"></i> Guaranteed turnaround windows according to University SLA standards.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services / Categories Section -->
    <section id="categories" class="py-5 bg-white border-bottom">
        <div class="container py-4">
            <div class="text-center mb-5">
                <span class="text-primary fw-bold text-uppercase small tracking-wide">Coverage</span>
                <h2 class="fw-bold text-dark mt-1">What Issues Can You Report?</h2>
                <p class="text-muted">Direct routing to IT specialists, electricians, plumbers, and estate carpenters.</p>
            </div>

            <div class="row g-4">
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="category-pill">
                        <i class="bi bi-wifi text-primary fs-2 mb-2 d-block"></i>
                        <h6 class="fw-bold text-dark mb-1">Wi-Fi & Internet</h6>
                        <small class="text-muted">Access points, LAN drops</small>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="category-pill">
                        <i class="bi bi-pc-display text-purple fs-2 mb-2 d-block" style="color: #7c3aed;"></i>
                        <h6 class="fw-bold text-dark mb-1">Computer Labs</h6>
                        <small class="text-muted">PCs, mice, monitors, OS</small>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="category-pill">
                        <i class="bi bi-projector text-warning fs-2 mb-2 d-block"></i>
                        <h6 class="fw-bold text-dark mb-1">Projectors & AV</h6>
                        <small class="text-muted">Lecture hall screens, audio</small>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="category-pill">
                        <i class="bi bi-lightning-charge text-danger fs-2 mb-2 d-block"></i>
                        <h6 class="fw-bold text-dark mb-1">Electrical & Power</h6>
                        <small class="text-muted">Sockets, sparks, breakers</small>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="category-pill">
                        <i class="bi bi-lightbulb text-warning fs-2 mb-2 d-block"></i>
                        <h6 class="fw-bold text-dark mb-1">Corridor Lighting</h6>
                        <small class="text-muted">Flickering tubes, switches</small>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="category-pill">
                        <i class="bi bi-droplet text-info fs-2 mb-2 d-block"></i>
                        <h6 class="fw-bold text-dark mb-1">Plumbing & Water</h6>
                        <small class="text-muted">Burst pipes, cistern leaks</small>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="category-pill">
                        <i class="bi bi-printer text-success fs-2 mb-2 d-block"></i>
                        <h6 class="fw-bold text-dark mb-1">Network Printers</h6>
                        <small class="text-muted">Paper jams, toner, queues</small>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="category-pill">
                        <i class="bi bi-door-closed text-secondary fs-2 mb-2 d-block"></i>
                        <h6 class="fw-bold text-dark mb-1">Furniture & Doors</h6>
                        <small class="text-muted">Broken desks, stuck locks</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="py-5" style="background-color: #f8fafc;">
        <div class="container py-4">
            <div class="text-center mb-5">
                <span class="text-primary fw-bold text-uppercase small tracking-wide">Transparent Lifecycle</span>
                <h2 class="fw-bold text-dark mt-1">How CampusFix Operates</h2>
            </div>

            <div class="row g-4 text-center">
                <div class="col-md-3">
                    <div class="feature-card">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 50px; height: 50px; font-weight: 700;">1</div>
                        <h5 class="fw-bold text-dark">Submit Report</h5>
                        <p class="text-muted small">Snap a photo, pick your building and room, describe the problem, and submit in seconds.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="feature-card">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 50px; height: 50px; font-weight: 700;">2</div>
                        <h5 class="fw-bold text-dark">Admin Triage</h5>
                        <p class="text-muted small">Administrators review impact, prioritize the ticket, and dispatch the right technician.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="feature-card">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 50px; height: 50px; font-weight: 700;">3</div>
                        <h5 class="fw-bold text-dark">Field Repair</h5>
                        <p class="text-muted small">Technician inspects images, visits location, records diagnostic notes, and marks resolved.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="feature-card">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 50px; height: 50px; font-weight: 700;">4</div>
                        <h5 class="fw-bold text-dark">Closure & Rating</h5>
                        <p class="text-muted small">You receive instant alerts, confirm the fix, rate the service, or reopen if needed.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4" style="background-color: #0f172a !important;">
        <div class="container text-center text-md-between d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
            <div class="small text-muted">
                &copy; <?= date('Y') ?> CampusFix – University Maintenance & IT Fault Management System. All rights reserved.
            </div>
            <div class="d-flex gap-3 small">
                <a href="<?= BASE_URL ?>auth/login.php" class="text-secondary text-decoration-none">Staff Login</a>
                <span class="text-secondary">•</span>
                <a href="<?= BASE_URL ?>docs/SRS.md" class="text-secondary text-decoration-none">Documentation</a>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

