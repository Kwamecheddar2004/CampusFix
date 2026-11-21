<?php
/**
 * CampusFix - Global HTML Header Partial
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';

$pageTitle = isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | CampusFix' : 'CampusFix - Campus Maintenance & IT Fault Management';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom CampusFix SaaS Stylesheet -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/style.css">
</head>
<body>
<div class="app-wrapper">
    <div class="sidebar-overlay"></div>

