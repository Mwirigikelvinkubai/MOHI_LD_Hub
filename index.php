<?php
/**
 * index.php — MOHI LD HUB Portal
 * Landing page showing all available modules as interactive cards.
 */

require_once 'config.php';

$pageTitle  = 'MOHI LD HUB';
$activePage = 'hub';

$pdo = getDB();

// Quick stats for display on hub cards
$totalStaff     = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
$totalTrainings = $pdo->query("SELECT COUNT(*) FROM trainings")->fetchColumn();
$totalResults   = $pdo->query("SELECT COUNT(*) FROM results")->fetchColumn();
$totalObjectives= $pdo->query("SELECT COUNT(*) FROM objectives")->fetchColumn();
$totalKPIs      = $pdo->query("SELECT COUNT(*) FROM kpis")->fetchColumn();
$totalAssets    = $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();

// Custom modules
$customModules = $pdo->query("SELECT * FROM hub_modules WHERE is_active=1 ORDER BY sort_order")->fetchAll();

require 'header.php';
?>

<!-- Hub welcome banner -->
<div class="card mb-4" style="background: linear-gradient(135deg, var(--bg2) 0%, var(--bg3) 100%); border-color: rgba(38,169,224,0.3);">
    <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
        <div>
            <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.12em; color:var(--blue); font-family:'Barlow',sans-serif; margin-bottom:6px;">
                Learning &amp; Development · Tech &amp; Data
            </div>
            <h2 style="font-family:'Barlow',sans-serif; font-size:26px; font-weight:800; color:var(--text); margin-bottom:6px;">
                Welcome to MOHI LD HUB
            </h2>
            <p style="color:var(--muted); font-size:13px; margin:0;">
                Your centralised workspace for L&amp;D Objectives, Inventory, and Staff Training Data.
            </p>
        </div>
        <div style="margin-left:auto; text-align:right; flex-shrink:0;">
            <div style="font-size:28px; font-family:'Barlow',sans-serif; font-weight:800; color:var(--blue);">
                <?= number_format($totalStaff) ?>
            </div>
            <div style="font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:0.08em;">Staff Tracked</div>
        </div>
    </div>
</div>

<!-- MODULE GRID -->
<div class="hub-grid">

    <!-- ── MODULE 1: OBJECTIVES ── -->
    <a href="obj_index.php" class="hub-card">
        <div class="hub-card-num">Module 1</div>
        <div class="hub-card-icon navy"><i class="bi bi-bullseye"></i></div>
        <div class="hub-card-title">Objectives</div>
        <div class="hub-card-desc">Track strategic objectives for the L&amp;D department with priority, ownership and status.</div>
        <div class="hub-card-items">
            <div class="hub-card-item">
                <i class="bi bi-graph-up-arrow"></i>
                <span><?= number_format($totalKPIs) ?> KPI<?= $totalKPIs != 1 ? 's' : '' ?> registered &nbsp;(1.1)</span>
            </div>
            <div class="hub-card-item">
                <i class="bi bi-grid-3x3"></i>
                <span>RACI Chart &nbsp;(1.1.1)</span>
            </div>
        </div>
        <i class="bi bi-arrow-right hub-card-arrow"></i>
    </a>

    <!-- ── MODULE 2: INVENTORY ── -->
    <a href="inv_assets.php" class="hub-card">
        <div class="hub-card-num">Module 2</div>
        <div class="hub-card-icon green"><i class="bi bi-box-seam-fill"></i></div>
        <div class="hub-card-title">Inventory</div>
        <div class="hub-card-desc">Manage L&amp;D department inventory including equipment, materials, and resources.</div>
        <div class="hub-card-items">
            <div class="hub-card-item">
                <i class="bi bi-tag"></i>
                <span><?= number_format($totalAssets) ?> asset<?= $totalAssets != 1 ? 's' : '' ?> logged &nbsp;(2.1)</span>
            </div>
        </div>
        <i class="bi bi-arrow-right hub-card-arrow"></i>
    </a>

    <!-- ── MODULE 3: STAFF TRAINING DATA ── -->
    <a href="train_dashboard.php" class="hub-card">
        <div class="hub-card-num">Module 3</div>
        <div class="hub-card-icon blue"><i class="bi bi-mortarboard-fill"></i></div>
        <div class="hub-card-title">Staff Training Data</div>
        <div class="hub-card-desc">Track staff training results, quiz scores, completions and reports across all programmes.</div>
        <div class="hub-card-items">
            <div class="hub-card-item">
                <i class="bi bi-people"></i>
                <span><?= number_format($totalStaff) ?> staff members</span>
            </div>
            <div class="hub-card-item">
                <i class="bi bi-journal-bookmark"></i>
                <span><?= number_format($totalTrainings) ?> training<?= $totalTrainings != 1 ? 's' : '' ?> &nbsp;·&nbsp; <?= number_format($totalResults) ?> results</span>
            </div>
        </div>
        <i class="bi bi-arrow-right hub-card-arrow"></i>
    </a>

    <!-- ── CUSTOM MODULES (from DB) ── -->
    <?php foreach ($customModules as $cm): ?>
    <a href="<?= htmlspecialchars($cm['link_url'] ?: '#') ?>"
       class="hub-card"
       <?= $cm['link_url'] && str_starts_with($cm['link_url'], 'http') ? 'target="_blank"' : '' ?>>
        <div class="hub-card-icon <?= htmlspecialchars($cm['color']) ?>">
            <i class="bi <?= htmlspecialchars($cm['icon']) ?>"></i>
        </div>
        <div class="hub-card-title"><?= htmlspecialchars($cm['title']) ?></div>
        <div class="hub-card-desc"><?= htmlspecialchars($cm['description'] ?? '') ?></div>
        <i class="bi bi-arrow-right hub-card-arrow"></i>
    </a>
    <?php endforeach; ?>

    <!-- ── ADD NEW MODULE ── -->
    <a href="modules.php" class="hub-card hub-card-add">
        <i class="bi bi-plus-circle"></i>
        <span>Add New Module</span>
        <div style="font-size:11px; color:rgba(122,170,200,0.5); text-align:center; padding:0 20px;">
            Extend the hub with your own modules and tools
        </div>
    </a>

    <!-- ── MANAGE USERS (admin only) ── -->
    <?php if (isAdmin()): ?>
    <a href="manage_users.php" class="hub-card">
        <div class="hub-card-icon navy"><i class="bi bi-shield-lock-fill"></i></div>
        <div class="hub-card-title">Manage Users</div>
        <div class="hub-card-desc">Add hub accounts, assign roles (Admin / Editor / Viewer), reset passwords and manage access.</div>
        <div class="hub-card-items">
            <?php
            $__uCount = getDB()->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
            $__uTotal = getDB()->query("SELECT COUNT(*) FROM users")->fetchColumn();
            ?>
            <div class="hub-card-item">
                <i class="bi bi-person-check"></i>
                <span><?= number_format($__uCount) ?> active account<?= $__uCount != 1 ? 's' : '' ?> of <?= $__uTotal ?> total</span>
            </div>
        </div>
        <i class="bi bi-arrow-right hub-card-arrow"></i>
    </a>
    <?php endif; ?>

</div>

<?php require 'footer.php'; ?>