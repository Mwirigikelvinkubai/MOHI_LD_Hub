<?php
/**
 * header.php — MOHI LD HUB shared navigation
 * Requires: $pageTitle, $activePage to be set before include
 */
require_once __DIR__ . '/auth.php';
requireAuth();

// Load custom modules for sidebar
$_db = getDB();
$_customModules = $_db->query("SELECT * FROM hub_modules WHERE is_active=1 ORDER BY sort_order ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrfToken() ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'MOHI LD HUB') ?> — MOHI LD HUB</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <!-- Google Fonts — Barlow + Roboto (MOHI brand) -->
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@500;600;700;800&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">

    <!-- MOHI Brand CSS -->
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<!-- Sidebar overlay (mobile) -->
<div id="sb-overlay"></div>

<!-- ============================================================
     SIDEBAR NAVIGATION
     ============================================================ -->
<aside class="sidebar" id="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <img src="assets/logo.svg" alt="MOHI Learning &amp; Development"
             style="width:100%; max-width:200px; height:auto; display:block; margin-bottom:0;">
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">

        <!-- Hub Home -->
        <a href="index.php" class="<?= $activePage === 'hub' ? 'active' : '' ?>">
            <i class="bi bi-house-fill"></i> Hub Home
        </a>

        <div class="sidebar-divider"></div>

        <!-- ── MODULE 1: OBJECTIVES ── -->
        <div class="nav-section-label">1. Objectives</div>

        <a href="obj_index.php" class="<?= $activePage === 'objectives' ? 'active' : '' ?>">
            <i class="bi bi-bullseye"></i> Objectives
        </a>

        <a href="obj_kpi.php" class="nav-sub <?= $activePage === 'kpi' ? 'active' : '' ?>">
            <i class="bi bi-graph-up-arrow"></i> 1.1 KPIs
        </a>

        <a href="obj_raci.php" class="nav-subsub <?= $activePage === 'raci' ? 'active' : '' ?>">
            <i class="bi bi-grid-3x3"></i> 1.1.1 RACI
        </a>

        <div class="sidebar-divider"></div>

        <!-- ── MODULE 2: INVENTORY ── -->
        <div class="nav-section-label">2. Inventory</div>

        <a href="inv_assets.php" class="<?= $activePage === 'assets' ? 'active' : '' ?>">
            <i class="bi bi-box-seam-fill"></i> 2.1 Assets
        </a>

        <div class="sidebar-divider"></div>

        <!-- ── MODULE 3: STAFF TRAINING DATA ── -->
        <div class="nav-section-label">3. Staff Training Data</div>

        <a href="train_dashboard.php" class="<?= $activePage === 'train_dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <a href="train_staff.php" class="nav-sub <?= $activePage === 'train_staff' ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i> Staff Directory
        </a>

        <a href="train_trainings.php" class="nav-sub <?= $activePage === 'train_trainings' ? 'active' : '' ?>">
            <i class="bi bi-journal-bookmark-fill"></i> Trainings
        </a>

        <a href="train_results.php" class="nav-sub <?= $activePage === 'train_results' ? 'active' : '' ?>">
            <i class="bi bi-clipboard2-check-fill"></i> Results
        </a>

        <a href="train_reports.php" class="nav-sub <?= $activePage === 'train_reports' ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-fill"></i> Reports
        </a>

        <?php if (!empty($_customModules)): ?>
        <div class="sidebar-divider"></div>
        <div class="nav-section-label">Custom Modules</div>
        <?php foreach ($_customModules as $cm): ?>
        <a href="<?= htmlspecialchars($cm['link_url'] ?: '#') ?>"
           class="<?= $activePage === 'cm_' . $cm['id'] ? 'active' : '' ?>"
           <?= $cm['link_url'] && str_starts_with($cm['link_url'], 'http') ? 'target="_blank"' : '' ?>>
            <i class="bi <?= htmlspecialchars($cm['icon']) ?>"></i>
            <?= htmlspecialchars($cm['title']) ?>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>

        <div class="sidebar-divider"></div>

        <!-- ── MANAGE MODULES ── -->
        <a href="modules.php" class="<?= $activePage === 'modules' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2"></i> Manage Modules
        </a>

    </nav>

    <div class="sidebar-footer">MOHI L&amp;D · <?= date('Y') ?></div>
</aside>

<!-- ============================================================
     MAIN CONTENT AREA
     ============================================================ -->
<div class="main">

    <!-- Topbar -->
    <div class="topbar">
        <!-- Hamburger (mobile only) -->
        <button class="sb-toggle" id="sb-toggle" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>

        <div class="topbar-left">
            <div>
                <div class="topbar-breadcrumb">Learning &amp; Development · Tech &amp; Data</div>
                <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'MOHI LD HUB') ?></div>
            </div>
        </div>
        <div class="topbar-right">
            <div class="topbar-date">
                <i class="bi bi-calendar3 me-1"></i><?= date('l, d M Y') ?>
            </div>
            <a href="login.php?logout=1"
               class="topbar-logout"
               onclick="return confirm('Sign out of MOHI LD HUB?')"
               title="Sign out">
                <i class="bi bi-box-arrow-right"></i>
                <span class="logout-label">Sign Out</span>
            </a>
        </div>
    </div>

    <!-- Flash message -->
    <div style="padding: 0 28px; margin-top: 16px;">
        <?= getFlash() ?>
    </div>

    <!-- Page content starts here -->
    <div class="content">