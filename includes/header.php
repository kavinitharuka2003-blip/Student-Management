<?php
/**
 * ==============================================================================
 * Global Layout Header & Sidebar Shell (includes/header.php)
 * ------------------------------------------------------------------------------
 * Initializes dependencies, enforces authentication guard, sets up dynamic
 * page metadata, and constructs the responsive sidebar navigation.
 * ==============================================================================
 */

// Load core configuration and helper modules
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce login requirement globally across all dashboard/CRUD pages
require_login();

// Fetch current user details
$current_user = get_current_user_data();

// Determine current active script for sidebar navigation state
$current_script = $_SERVER['SCRIPT_NAME'];

function is_nav_active(string $keyword): string {
    global $current_script;
    return (strpos($current_script, $keyword) !== false) ? 'active' : '';
}

// Fallback for page title
if (!isset($page_title)) {
    $page_title = 'Student & Course Management System';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Plymouth University DLE Coursework - Student and Course Management System">
    <title><?= e($page_title) ?> | SCMS</title>
    
    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons 1.11.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Chart.js (CDN for analytical dashboards) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    
    <!-- Custom Bespoke Styling -->
    <link rel="stylesheet" href="<?= e(get_app_url('assets/css/style.css')) ?>">
</head>
<body>

<div class="app-wrapper">
    <!-- Left Navigation Sidebar -->
    <aside class="app-sidebar" id="appSidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <div>
                <span class="sidebar-brand-text">SCMS Portal</span>
                <span class="sidebar-brand-sub">Academic Administration</span>
            </div>
        </div>

        <ul class="sidebar-menu">
            <li class="sidebar-category">Overview</li>
            <li>
                <a href="<?= e(get_app_url('dashboard.php')) ?>" class="sidebar-link <?= is_nav_active('dashboard') ?>">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li class="sidebar-category">Academic Records</li>
            <li>
                <a href="<?= e(get_app_url('students/list.php')) ?>" class="sidebar-link <?= is_nav_active('students') ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>Students</span>
                </a>
            </li>
            <li>
                <a href="<?= e(get_app_url('courses/list.php')) ?>" class="sidebar-link <?= is_nav_active('courses') ?>">
                    <i class="bi bi-journal-bookmark-fill"></i>
                    <span>Courses</span>
                </a>
            </li>
            <li>
                <a href="<?= e(get_app_url('enrollments/list.php')) ?>" class="sidebar-link <?= is_nav_active('enrollments') ?>">
                    <i class="bi bi-card-checklist"></i>
                    <span>Enrollments</span>
                </a>
            </li>

            <li class="sidebar-category">Preferences</li>
            <li>
                <a href="<?= e(get_app_url('auth/profile.php')) ?>" class="sidebar-link <?= is_nav_active('profile') ?>">
                    <i class="bi bi-person-gear"></i>
                    <span>My Profile</span>
                </a>
            </li>

            <?php if (is_admin()): ?>
            <li class="sidebar-category">Administration</li>
            <li>
                <span class="sidebar-link text-muted opacity-75" title="Active Admin Mode">
                    <i class="bi bi-shield-lock-fill text-warning"></i>
                    <span>Full Admin Mode</span>
                </span>
            </li>
            <?php endif; ?>
        </ul>

        <!-- Authenticated User Profile Footer -->
        <div class="sidebar-user">
            <div class="user-avatar">
                <?= strtoupper(substr($current_user['full_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="user-info">
                <div class="user-name" title="<?= e($current_user['full_name']) ?>"><?= e($current_user['full_name']) ?></div>
                <span class="user-role-badge <?= is_admin() ? 'bg-primary text-white' : 'bg-info text-dark' ?>">
                    <?= is_admin() ? 'Administrator' : 'Lecturer' ?>
                </span>
            </div>
            <a href="<?= e(get_app_url('auth/logout.php')) ?>" class="text-secondary hover-white p-1" title="Log Out">
                <i class="bi bi-box-arrow-right fs-5"></i>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="app-main">
        <!-- Sticky Top Bar -->
        <header class="app-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-light d-lg-none" id="sidebarToggle" type="button" aria-label="Toggle Sidebar">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div class="d-none d-sm-block">
                    <h5 class="mb-0 fw-bold font-heading text-dark"><?= e($page_title) ?></h5>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill d-none d-md-inline-flex align-items-center gap-1.5 font-monospace">
                    <i class="bi bi-calendar3"></i> <?= date('D, d M Y') ?>
                </span>
                
                <div class="dropdown">
                    <button class="btn btn-light d-flex align-items-center gap-2 rounded-pill px-3 py-1.5 border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle text-primary"></i>
                        <span class="fw-semibold small d-none d-sm-inline"><?= e($current_user['username']) ?></span>
                        <i class="bi bi-chevron-down small text-muted"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                        <li class="dropdown-header">
                            <h6 class="mb-0 text-dark fw-bold"><?= e($current_user['full_name']) ?></h6>
                            <small class="text-muted"><?= e($current_user['email']) ?></small>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2" href="<?= e(get_app_url('auth/profile.php')) ?>">
                                <i class="bi bi-person-gear text-primary"></i> Account Settings
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger d-flex align-items-center gap-2" href="<?= e(get_app_url('auth/logout.php')) ?>">
                                <i class="bi bi-box-arrow-right"></i> Sign Out
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Main Body Container -->
        <div class="app-body">
            <!-- Dismissible Flash Message Alerts -->
            <?= render_flash_messages() ?>
