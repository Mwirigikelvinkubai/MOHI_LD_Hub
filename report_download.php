<?php
/**
 * report_download.php — Printable Group Report
 * Works for: completion, training_detail, dept_summary, non_participants
 * Filtered by: department, workstation, training
 * Opens in new tab — Print → Save as PDF
 */

require_once 'config.php';
require_once 'auth.php';
requireAuth();

$pdo = getDB();

$reportType  = $_GET['report']       ?? 'completion';
$trainingId  = (int)($_GET['training_id'] ?? 0);
$department  = clean($_GET['dept']        ?? '');
$workstation = clean($_GET['workstation'] ?? '');

// ── Fetch context labels ──────────────────────────────────────────────
$trainingName = '';
if ($trainingId) {
    $t = $pdo->prepare("SELECT name FROM trainings WHERE id=?");
    $t->execute([$trainingId]);
    $trainingName = $t->fetchColumn() ?: '';
}

// ── Build filter description for cover band ───────────────────────────
$filterParts = [];
if ($trainingName) $filterParts[] = 'Training: ' . $trainingName;
if ($department)   $filterParts[] = 'Department: ' . $department;
if ($workstation)  $filterParts[] = 'Work Station: ' . $workstation;
$filterDesc = $filterParts ? implode('   ·   ', $filterParts) : 'All Staff / All Trainings';

// ── Report titles ─────────────────────────────────────────────────────
$reportTitles = [
    'completion'       => 'Staff Training Completion Overview',
    'training_detail'  => 'Training Detail Report',
    'dept_summary'     => 'Department Performance Summary',
    'non_participants' => 'Non-Participants Report',
];
$reportTitle = $reportTitles[$reportType] ?? 'Report';

// ── Queries ───────────────────────────────────────────────────────────

$reportData = [];

if ($reportType === 'completion') {
    $where = "WHERE 1=1"; $params = [];
    if ($department)  { $where .= " AND s.department=?";  $params[] = $department; }
    if ($workstation) { $where .= " AND s.workstation=?"; $params[] = $workstation; }
    if ($trainingId)  { $where .= " AND t.id=?";          $params[] = $trainingId; }

    $stmt = $pdo->prepare("
        SELECT s.staff_no, s.full_name, s.email, s.department, s.workstation, s.job_title,
               t.name as training_name,
               COUNT(r.id) as sub_courses_done,
               (SELECT COUNT(*) FROM sub_courses WHERE training_id=t.id) as sub_courses_total,
               ROUND(AVG(r.score),1) as avg_score,
               SUM(CASE WHEN r.score >= sc.pass_mark THEN 1 ELSE 0 END) as passed,
               SUM(CASE WHEN r.score <  sc.pass_mark THEN 1 ELSE 0 END) as failed
        FROM staff s
        CROSS JOIN trainings t
        LEFT JOIN results r    ON r.staff_id=s.id AND r.training_id=t.id
        LEFT JOIN sub_courses sc ON sc.id=r.sub_course_id
        $where
        GROUP BY s.id, t.id
        ORDER BY s.department, s.full_name, t.name
    ");
    $stmt->execute($params);
    $reportData = $stmt->fetchAll();
}

elseif ($reportType === 'training_detail' && $trainingId) {
    $where = "WHERE r.training_id=?"; $params = [$trainingId];
    if ($department)  { $where .= " AND s.department=?";  $params[] = $department; }
    if ($workstation) { $where .= " AND s.workstation=?"; $params[] = $workstation; }

    $stmt = $pdo->prepare("
        SELECT s.staff_no, s.full_name, s.email, s.department, s.workstation, s.job_title,
               sc.name as sub_course, sc.pass_mark, r.score, r.date_taken,
               CASE WHEN r.score >= sc.pass_mark THEN 'Pass' ELSE 'Fail' END as status
        FROM results r
        JOIN staff s       ON s.id  = r.staff_id
        JOIN sub_courses sc ON sc.id = r.sub_course_id
        $where
        ORDER BY s.department, s.full_name, sc.name
    ");
    $stmt->execute($params);
    $reportData = $stmt->fetchAll();
}

elseif ($reportType === 'dept_summary') {
    // Training columns
    if ($trainingId) {
        $matrixTrainings = $pdo->prepare("SELECT id, name FROM trainings WHERE id = ?");
        $matrixTrainings->execute([$trainingId]);
        $matrixTrainings = $matrixTrainings->fetchAll();
    } else {
        $matrixTrainings = $pdo->query("SELECT id, name FROM trainings ORDER BY name")->fetchAll();
    }

    // Sub-courses per training
    $tIds = array_column($matrixTrainings, 'id');
    $matrixSubCourses = [];
    if ($tIds) {
        $in = implode(',', array_fill(0, count($tIds), '?'));
        $scStmt = $pdo->prepare("SELECT id, training_id, name FROM sub_courses WHERE training_id IN ($in) ORDER BY training_id, name");
        $scStmt->execute($tIds);
        foreach ($scStmt->fetchAll() as $sc) {
            $matrixSubCourses[$sc['training_id']][] = $sc;
        }
    }

    // Staff rows filtered by dept/workstation
    $swhere = "WHERE 1=1"; $sparams = [];
    if ($department)  { $swhere .= " AND department  = ?"; $sparams[] = $department; }
    if ($workstation) { $swhere .= " AND workstation = ?"; $sparams[] = $workstation; }
    $staffStmt = $pdo->prepare("SELECT id, full_name, email, department FROM staff $swhere ORDER BY department, full_name");
    $staffStmt->execute($sparams);
    $matrixStaff = $staffStmt->fetchAll();

    // Completion map: staff_id => sub_course_id => true
    $doneMap = [];
    foreach ($pdo->query("SELECT DISTINCT staff_id, sub_course_id FROM results")->fetchAll() as $row) {
        $doneMap[$row['staff_id']][$row['sub_course_id']] = true;
    }

    $reportData = $matrixStaff;
}

elseif ($reportType === 'non_participants' && $trainingId) {
    $where = "WHERE 1=1"; $params = [];
    if ($department)  { $where .= " AND s.department=?";  $params[] = $department; }
    if ($workstation) { $where .= " AND s.workstation=?"; $params[] = $workstation; }
    $params[] = $trainingId;

    $stmt = $pdo->prepare("
        SELECT s.staff_no, s.full_name, s.email, s.job_title, s.department, s.workstation
        FROM staff s
        $where
        AND s.id NOT IN (SELECT DISTINCT staff_id FROM results WHERE training_id=?)
        ORDER BY s.department, s.full_name
    ");
    $stmt->execute($params);
    $reportData = $stmt->fetchAll();
}

// ── Summary counts ─────────────────────────────────────────────────────
$totalRows   = count($reportData);
$uniqueStaff = [];
if (in_array($reportType, ['completion','training_detail','non_participants'])) {
    $uniqueStaff = array_unique(array_column($reportData, 'email'));
}

$generatedAt = date('d M Y, H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($reportTitle) ?> — MOHI L&D</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Roboto', sans-serif;
            font-size: 12.5px;
            background: #f0f4f8;
            color: #1a2a3a;
            padding: 28px 12px;
        }

        .page {
            max-width: 1180px;
            margin: 0 auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.12);
            overflow: hidden;
        }

        /* Header */
        .doc-header {
            background: #002F66;
            padding: 26px 36px 20px;
            position: relative;
        }
        .doc-header::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, #26A9E0, #8BC53F);
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
        }
        .org-name {
            font-family: 'Barlow', sans-serif;
            font-size: 19px; font-weight: 800; color: #fff;
        }
        .org-tagline {
            font-size: 10px; color: #26A9E0; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.12em; margin-top: 3px;
        }
        .doc-title-block { text-align: right; }
        .doc-type-lbl {
            font-size: 10px; color: rgba(255,255,255,0.45);
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.14em;
            font-family: 'Barlow', sans-serif;
        }
        .doc-title {
            font-family: 'Barlow', sans-serif;
            font-size: 20px; font-weight: 800; color: #fff; margin-top: 2px;
        }

        /* Filter band */
        .filter-band {
            background: #003a7a;
            padding: 12px 36px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            font-size: 11.5px;
        }
        .filter-lbl {
            font-family: 'Barlow', sans-serif;
            font-size: 9.5px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.12em;
            color: rgba(122,170,210,0.7);
            margin-right: 4px;
        }
        .filter-pill {
            background: rgba(38,169,224,0.18);
            color: #90d0f0;
            border: 1px solid rgba(38,169,224,0.3);
            border-radius: 20px;
            padding: 3px 11px;
            font-size: 11px;
            font-weight: 500;
        }

        /* Stats row */
        .stats-row {
            display: flex;
            border-bottom: 2px solid #e8eef4;
        }
        .stat-box {
            flex: 1; padding: 14px 18px; text-align: center;
            border-right: 1px solid #e8eef4;
        }
        .stat-box:last-child { border-right: none; }
        .stat-num {
            font-family: 'Barlow', sans-serif;
            font-size: 24px; font-weight: 800; color: #002F66; line-height: 1;
        }
        .stat-num.blue  { color: #0089BA; }
        .stat-num.green { color: #5a9e1e; }
        .stat-num.gold  { color: #c48a00; }
        .stat-lbl {
            font-size: 9.5px; color: #7a94b0; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.08em;
            margin-top: 3px; font-family: 'Barlow', sans-serif;
        }

        /* Body */
        .doc-body { padding: 24px 36px; }

        /* Section header (used for dept grouping) */
        .section-hdr {
            background: #f0f5fb;
            border: 1px solid #dde6f0;
            border-bottom: none;
            border-radius: 7px 7px 0 0;
            padding: 10px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 20px;
        }
        .section-hdr:first-child { margin-top: 0; }
        .section-name {
            font-family: 'Barlow', sans-serif;
            font-size: 13px; font-weight: 700; color: #002F66;
        }
        .section-meta { font-size: 11px; color: #7a94b0; }

        /* Table */
        .data-tbl {
            width: 100%; border-collapse: collapse;
            border: 1px solid #dde6f0;
            border-radius: 0 0 7px 7px;
            overflow: hidden;
            margin-bottom: 0;
            font-size: 12px;
        }
        .data-tbl thead th {
            background: #f8fafc;
            color: #7a94b0;
            font-family: 'Barlow', sans-serif;
            font-size: 9px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.1em;
            padding: 7px 12px; text-align: left;
            border-bottom: 1px solid #dde6f0;
        }
        .data-tbl tbody td {
            padding: 8px 12px;
            border-bottom: 1px solid #f0f4f8;
            vertical-align: middle;
        }
        .data-tbl tbody tr:last-child td { border-bottom: none; }
        .data-tbl tbody tr:nth-child(even) td { background: #fafcff; }

        .name-cell strong { color: #002F66; font-size: 12.5px; }
        .email-cell { color: #26A9E0; font-size: 11px; }
        .muted { color: #7a94b0; }

        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 20px;
            font-size: 9.5px; font-weight: 700; font-family: 'Barlow', sans-serif;
            letter-spacing: 0.04em;
        }
        .badge-pass { background:#eef7e2; color:#4a8a0e; border:1px solid #c3e08a; }
        .badge-fail { background:#fef0f0; color:#c0392b; border:1px solid #f5b7b1; }
        .badge-dept { background:rgba(0,47,102,0.07); color:#002F66; border:1px solid rgba(0,47,102,0.15); }

        .bar-wrap {
            display: inline-block; background: #e8eef4;
            border-radius: 20px; height: 5px; width: 50px;
            vertical-align: middle; margin-left: 5px;
        }
        .bar-fill { height: 5px; border-radius: 20px; background: linear-gradient(90deg,#26A9E0,#8BC53F); }
        .bar-fill.low { background: linear-gradient(90deg,#e74c3c,#f1948a); }
        .bar-fill.mid { background: linear-gradient(90deg,#f39c12,#f5d06a); }

        .empty {
            text-align: center; padding: 36px; color: #7a94b0; font-size: 13px;
        }

        /* Standalone table (no section grouping) */
        .standalone-tbl {
            border: 1px solid #dde6f0; border-radius: 8px; overflow: hidden;
        }

        /* Footer */
        .doc-footer {
            border-top: 1px solid #e8eef4; padding: 12px 36px;
            display: flex; justify-content: space-between;
            font-size: 10px; color: #aabccc; flex-wrap: wrap; gap: 6px;
        }
        .doc-footer strong { color: #7a94b0; }

        /* Print bar */
        .print-bar {
            max-width: 1180px; margin: 0 auto 16px;
            display: flex; gap: 10px; align-items: center; justify-content: flex-end;
        }
        .btn-print {
            background: linear-gradient(135deg,#26A9E0,#0089BA);
            color:#fff; border:none; border-radius:7px; padding:9px 20px;
            font-family:'Barlow',sans-serif; font-size:13px; font-weight:600;
            cursor:pointer; display:inline-flex; align-items:center; gap:7px;
            box-shadow:0 3px 12px rgba(38,169,224,0.3); text-decoration:none;
        }
        .btn-back {
            background:transparent; color:#7a94b0; border:1px solid #ccdde8;
            border-radius:7px; padding:8px 16px;
            font-family:'Barlow',sans-serif; font-size:13px; font-weight:500;
            cursor:pointer; text-decoration:none;
            display:inline-flex; align-items:center; gap:6px;
        }

        /* ── Share button ── */
        .share-wrap { position: relative; }

        .btn-share {
            background: #fff; color: #002F66;
            border: 1.5px solid #ccdde8; border-radius: 7px;
            padding: 9px 18px; font-family: 'Barlow', sans-serif;
            font-size: 13px; font-weight: 600; cursor: pointer;
            display: inline-flex; align-items: center; gap: 7px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        .btn-share:hover { border-color: #26A9E0; color: #26A9E0; }

        .share-menu {
            display: none; position: absolute; right: 0; top: calc(100% + 8px);
            background: #fff; border: 1.5px solid #dde8f0;
            border-radius: 10px; box-shadow: 0 8px 32px rgba(0,47,102,0.15);
            min-width: 220px; z-index: 999; overflow: hidden;
        }
        .share-menu.open { display: block; }

        .share-hint {
            font-size: 10.5px; color: #7a94b0; padding: 10px 14px 6px;
            border-bottom: 1px solid #f0f4f8; font-style: italic;
        }

        .share-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 16px; font-size: 13px; color: #1a2a3a;
            font-family: 'Barlow', sans-serif; font-weight: 500;
            text-decoration: none; background: none; border: none;
            width: 100%; cursor: pointer; transition: background 0.15s;
        }
        .share-item:hover { background: #f4f8ff; color: #002F66; }

        .share-icon {
            width: 26px; height: 26px; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700; flex-shrink: 0;
            background: #f0f4f8; color: #1a2a3a;
        }
        .linkedin-icon { background: #0077b5; color: #fff; font-family: Georgia, serif; font-size: 14px; }
        .wa-icon { background: #25d366; color: #fff; }
        .x-icon { background: #000; color: #fff; font-size: 12px; }
        .email-icon { background: #26A9E0; color: #fff; }
        .link-icon { background: #f0f4f8; }

        .share-note {
            font-size: 10px; color: #7a94b0; padding: 8px 14px 10px;
            border-top: 1px solid #f0f4f8; line-height: 1.5;
        }

        @media print {
            body { background:#fff; padding:0; }
            .page { box-shadow:none; border-radius:0; max-width:100%; }
            .print-bar { display:none !important; }
            .section-hdr, .standalone-tbl, .data-tbl { break-inside: avoid; }
            @page { margin:8mm 6mm; size:A4 landscape; }
            .page { box-shadow:none; border-radius:0; max-width:100%; }
            .doc-body { padding:16px 20px; }
        }
    </style>
</head>
<body>

<div class="print-bar">
    <a href="javascript:history.back()" class="btn-back">← Back</a>

    <!-- Share dropdown -->
    <div class="share-wrap" id="shareWrap">
        <button class="btn-share" onclick="toggleShare(event)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            Share
        </button>
        <div class="share-menu" id="shareMenu">
            <div class="share-hint">Save as PDF first, then share the file</div>
            <!-- Native share (works on mobile) -->
            <button class="share-item" id="nativeShareBtn" onclick="nativeShare()" style="display:none">
                <span class="share-icon">📤</span> Share via Device
            </button>
            <!-- LinkedIn -->
            <a class="share-item" id="linkedinBtn" href="#" target="_blank" onclick="shareLinkedIn()">
                <span class="share-icon linkedin-icon">in</span> Share on LinkedIn
            </a>
            <!-- WhatsApp -->
            <a class="share-item" id="waBtn" href="#" target="_blank" onclick="shareWhatsApp(event)">
                <span class="share-icon wa-icon">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                </span> Share on WhatsApp
            </a>
            <!-- Twitter/X -->
            <a class="share-item" id="twitterBtn" href="#" target="_blank" onclick="shareTwitter(event)">
                <span class="share-icon x-icon">𝕏</span> Share on X / Twitter
            </a>
            <!-- Email -->
            <a class="share-item" href="#" onclick="shareEmail(event)">
                <span class="share-icon email-icon">✉</span> Share via Email
            </a>
            <!-- Copy link -->
            <button class="share-item" onclick="copyLink()">
                <span class="share-icon link-icon">🔗</span> <span id="copyLinkTxt">Copy Report Link</span>
            </button>
            <div class="share-note">
                <strong>Instagram / TikTok:</strong> Save as PDF, then upload the file directly in-app.
            </div>
        </div>
    </div>

    <button class="btn-print" onclick="window.print()">🖨 Print / Save as PDF</button>
</div>

<div class="page">

    <!-- Header -->
    <div class="doc-header">
        <div class="header-top">
            <div>
                <div class="org-name">Missions of Hope International</div>
                <div class="org-tagline">Learning &amp; Development Department</div>
            </div>
            <div class="doc-title-block">
                <div class="doc-type-lbl">L&amp;D Report</div>
                <div class="doc-title"><?= htmlspecialchars($reportTitle) ?></div>
            </div>
        </div>
    </div>

    <!-- Filter band -->
    <div class="filter-band">
        <span class="filter-lbl">Filters</span>
        <?php foreach ($filterParts as $fp): ?>
            <span class="filter-pill"><?= htmlspecialchars($fp) ?></span>
        <?php endforeach; ?>
        <?php if (!$filterParts): ?>
            <span class="filter-pill">All Records</span>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <?php if ($reportType === 'dept_summary'):
            $deptCount = count(array_unique(array_column($matrixStaff, 'department')));
            $totDone = 0; $totTotal = 0;
            foreach ($matrixStaff as $s) {
                foreach ($matrixTrainings as $tr) {
                    foreach (($matrixSubCourses[$tr['id']] ?? []) as $sc) {
                        $totTotal++;
                        if (!empty($doneMap[$s['id']][$sc['id']])) $totDone++;
                    }
                }
            }
            $completionRate = $totTotal > 0 ? round($totDone/$totTotal*100) : 0;
            $totalSubCourses = array_sum(array_map(fn($t) => count($matrixSubCourses[$t['id']] ?? []), $matrixTrainings));
        ?>
            <div class="stat-box">
                <div class="stat-num blue"><?= $deptCount ?></div>
                <div class="stat-lbl">Departments</div>
            </div>
            <div class="stat-box">
                <div class="stat-num"><?= count($matrixStaff) ?></div>
                <div class="stat-lbl">Staff</div>
            </div>
            <div class="stat-box">
                <div class="stat-num"><?= count($matrixTrainings) ?></div>
                <div class="stat-lbl">Trainings</div>
            </div>
            <div class="stat-box">
                <div class="stat-num"><?= $totalSubCourses ?></div>
                <div class="stat-lbl">Sub-Courses</div>
            </div>
            <div class="stat-box">
                <div class="stat-num <?= $completionRate>=70?'green':($completionRate>=50?'gold':'') ?>"><?= $completionRate ?>%</div>
                <div class="stat-lbl">Completion Rate</div>
            </div>
        <?php elseif ($reportType === 'non_participants'): ?>
            <div class="stat-box">
                <div class="stat-num blue"><?= count($uniqueStaff) ?></div>
                <div class="stat-lbl">Not Yet Trained</div>
            </div>
            <?php
                $deptCount = count(array_unique(array_column($reportData,'department')));
            ?>
            <div class="stat-box">
                <div class="stat-num"><?= $deptCount ?></div>
                <div class="stat-lbl">Departments</div>
            </div>
            <div class="stat-box">
                <div class="stat-num gold"><?= htmlspecialchars($trainingName ?: '—') ?></div>
                <div class="stat-lbl">Training</div>
            </div>
        <?php else: ?>
            <?php
                $uStaff   = count(array_unique(array_column($reportData,'email')));
                $allScores = array_filter(array_column($reportData,'avg_score'), fn($v)=>$v!==null);
                $avgS      = $allScores ? round(array_sum($allScores)/count($allScores),1) : 0;
                $totP      = array_sum(array_column($reportData,'passed'));
                $totF      = array_sum(array_column($reportData,'failed'));
                $pRate     = ($totP+$totF) > 0 ? round($totP/($totP+$totF)*100,1) : 0;
            ?>
            <div class="stat-box">
                <div class="stat-num blue"><?= $uStaff ?></div>
                <div class="stat-lbl">Staff</div>
            </div>
            <div class="stat-box">
                <div class="stat-num"><?= $totalRows ?></div>
                <div class="stat-lbl">Records</div>
            </div>
            <div class="stat-box">
                <div class="stat-num green"><?= $totP ?></div>
                <div class="stat-lbl">Passed</div>
            </div>
            <div class="stat-box">
                <div class="stat-num gold"><?= $avgS ?>%</div>
                <div class="stat-lbl">Avg Score</div>
            </div>
            <div class="stat-box">
                <div class="stat-num <?= $pRate>=70?'green':($pRate>=50?'gold':'') ?>"><?= $pRate ?>%</div>
                <div class="stat-lbl">Pass Rate</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Body -->
    <div class="doc-body">

    <?php if (empty($reportData)): ?>
        <div class="empty">No data found for the selected filters.</div>

    <!-- ── COMPLETION OVERVIEW ── -->
    <?php elseif ($reportType === 'completion'):
        // Group by department
        $byDept = [];
        foreach ($reportData as $row) {
            $byDept[$row['department'] ?: 'Unassigned'][] = $row;
        }
        foreach ($byDept as $dname => $rows):
            $dStaff = count(array_unique(array_column($rows,'email')));
    ?>
        <div class="section-hdr">
            <div>
                <span class="section-name"><?= htmlspecialchars($dname) ?></span>
                <span class="section-meta" style="margin-left:10px"><?= $dStaff ?> staff</span>
            </div>
        </div>
        <table class="data-tbl" style="margin-bottom:20px">
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Job Title</th>
                    <th>Work Station</th>
                    <th>Training</th>
                    <th>Done</th>
                    <th>Avg Score</th>
                    <th>Passed</th>
                    <th>Failed</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):
                $pct = $r['sub_courses_total']>0 ? round($r['sub_courses_done']/$r['sub_courses_total']*100) : 0;
            ?>
                <tr>
                    <td class="name-cell">
                        <strong><?= htmlspecialchars($r['full_name']) ?></strong>
                    </td>
                    <td class="email-cell"><?= htmlspecialchars($r['email']) ?></td>
                    <td class="muted"><?= htmlspecialchars($r['job_title']) ?></td>
                    <td class="muted"><?= htmlspecialchars($r['workstation']) ?></td>
                    <td><?= htmlspecialchars($r['training_name']) ?></td>
                    <td>
                        <?= $r['sub_courses_done'] ?>/<?= $r['sub_courses_total'] ?>
                        <span class="bar-wrap"><span class="bar-fill" style="width:<?= $pct ?>%"></span></span>
                    </td>
                    <td><?= $r['avg_score'] ?? '—' ?>%</td>
                    <td><?= (int)$r['passed'] > 0 ? '<span class="badge badge-pass">'.(int)$r['passed'].'</span>' : '—' ?></td>
                    <td><?= (int)$r['failed'] > 0 ? '<span class="badge badge-fail">'.(int)$r['failed'].'</span>' : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endforeach; ?>

    <!-- ── TRAINING DETAIL ── -->
    <?php elseif ($reportType === 'training_detail'):
        $byDept = [];
        foreach ($reportData as $row) {
            $byDept[$row['department'] ?: 'Unassigned'][] = $row;
        }
        foreach ($byDept as $dname => $rows):
            $dStaff = count(array_unique(array_column($rows,'email')));
    ?>
        <div class="section-hdr">
            <div>
                <span class="section-name"><?= htmlspecialchars($dname) ?></span>
                <span class="section-meta" style="margin-left:10px"><?= $dStaff ?> staff</span>
            </div>
        </div>
        <table class="data-tbl" style="margin-bottom:20px">
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Work Station</th>
                    <th>Sub-Course</th>
                    <th>Score</th>
                    <th>Pass Mark</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="name-cell"><strong><?= htmlspecialchars($r['full_name']) ?></strong></td>
                    <td class="email-cell"><?= htmlspecialchars($r['email']) ?></td>
                    <td class="muted"><?= htmlspecialchars($r['workstation']) ?></td>
                    <td><?= htmlspecialchars($r['sub_course']) ?></td>
                    <td>
                        <strong><?= $r['score'] ?>%</strong>
                        <span class="bar-wrap">
                            <span class="bar-fill <?= $r['score']<50?'low':($r['score']<70?'mid':'') ?>" style="width:<?= min($r['score'],100) ?>%"></span>
                        </span>
                    </td>
                    <td class="muted"><?= $r['pass_mark'] ?>%</td>
                    <td><span class="badge <?= $r['status']==='Pass'?'badge-pass':'badge-fail' ?>"><?= $r['status'] ?></span></td>
                    <td class="muted"><?= htmlspecialchars($r['date_taken'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endforeach; ?>

    <!-- ── DEPARTMENT SUMMARY ── -->
    <?php elseif ($reportType === 'dept_summary'):
        $staffByDept = [];
        foreach ($matrixStaff as $s) {
            $staffByDept[$s['department'] ?: 'Unassigned'][] = $s;
        }
    ?>
        <!-- Legend -->
        <div style="display:flex;gap:14px;margin-bottom:18px;font-size:11.5px;align-items:center;flex-wrap:wrap;">
            <span style="color:#7a94b0">Legend:</span>
            <span style="color:#4a8a0e;font-weight:700;font-size:16px">✓</span><span style="color:#4a8a0e;font-weight:600"> Sub-course done</span>
            &nbsp;&nbsp;
            <span style="color:#c0392b;font-weight:700;font-size:16px">✗</span><span style="color:#c0392b;font-weight:600"> Not done</span>
        </div>

        <?php foreach ($staffByDept as $deptName => $deptStaff): ?>
        <div style="margin-bottom:28px;">
            <div class="section-hdr">
                <span class="section-name"><?= htmlspecialchars($deptName) ?></span>
                <span class="section-meta"><?= count($deptStaff) ?> staff</span>
            </div>
            <div style="overflow-x:auto;border:1px solid #dde6f0;border-top:none;border-radius:0 0 7px 7px;">
            <table class="data-tbl" style="min-width:100%;font-size:11.5px;">
                <thead>
                    <!-- Row 1: Training group headers -->
                    <tr>
                        <th rowspan="2" style="min-width:140px;vertical-align:middle;white-space:nowrap">Full Name</th>
                        <?php foreach ($matrixTrainings as $tr):
                            $scCount = count($matrixSubCourses[$tr['id']] ?? []);
                            if ($scCount === 0) continue;
                        ?>
                        <th colspan="<?= $scCount ?>"
                            style="text-align:center;background:rgba(0,47,102,0.06);
                                   border-left:2px solid #dde6f0;font-size:8.5px;
                                   color:#002F66;font-weight:700;padding:5px 6px;white-space:nowrap">
                            <?= htmlspecialchars($tr['name']) ?>
                            <span style="font-weight:400;color:#7a94b0;margin-left:3px">(<?= $scCount ?>)</span>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                    <!-- Row 2: Sub-course headers -->
                    <tr>
                        <?php foreach ($matrixTrainings as $tr):
                            foreach (($matrixSubCourses[$tr['id']] ?? []) as $sc):
                        ?>
                        <th style="text-align:center;width:52px;max-width:65px;
                                   border-left:1px solid #dde6f0;font-weight:600;
                                   color:#7a94b0;background:#f8fafc;
                                   padding:0;height:100px;vertical-align:bottom;">
                            <div style="writing-mode:vertical-rl;transform:rotate(180deg);
                                        padding:6px 4px;font-size:8px;line-height:1.2;
                                        word-break:break-word;max-height:96px;
                                        overflow:hidden;display:block;"><?= htmlspecialchars($sc['name']) ?></div>
                        </th>
                        <?php endforeach; endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($deptStaff as $s):
                    $scTotal = 0; $scDone = 0;
                    foreach ($matrixTrainings as $tr) {
                        foreach (($matrixSubCourses[$tr['id']] ?? []) as $sc) {
                            $scTotal++;
                            if (!empty($doneMap[$s['id']][$sc['id']])) $scDone++;
                        }
                    }
                ?>
                    <tr>
                        <td class="name-cell">
                            <strong><?= htmlspecialchars($s['full_name']) ?></strong>
                            <div style="font-size:8.5px;color:#7a94b0;margin-top:1px"><?= $scDone ?>/<?= $scTotal ?></div>
                        </td>
                        <?php foreach ($matrixTrainings as $tr):
                            foreach (($matrixSubCourses[$tr['id']] ?? []) as $sc):
                        ?>
                        <td style="text-align:center;padding:5px 2px;border-left:1px solid #f0f4f8;width:52px;">
                            <?php if (!empty($doneMap[$s['id']][$sc['id']])): ?>
                                <span style="color:#4a8a0e;font-size:15px;font-weight:700">✓</span>
                            <?php else: ?>
                                <span style="color:#c0392b;font-size:15px;font-weight:700">✗</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endforeach; ?>

    <!-- ── NON-PARTICIPANTS ── -->
    <?php elseif ($reportType === 'non_participants'):
        $byDept = [];
        foreach ($reportData as $row) {
            $byDept[$row['department'] ?: 'Unassigned'][] = $row;
        }
        foreach ($byDept as $dname => $rows):
    ?>
        <div class="section-hdr">
            <span class="section-name"><?= htmlspecialchars($dname) ?></span>
            <span class="section-meta" style="margin-left:10px"><?= count($rows) ?> staff not yet trained</span>
        </div>
        <table class="data-tbl" style="margin-bottom:20px">
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Staff No</th>
                    <th>Job Title</th>
                    <th>Work Station</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="name-cell"><strong><?= htmlspecialchars($r['full_name']) ?></strong></td>
                    <td class="email-cell"><?= htmlspecialchars($r['email']) ?></td>
                    <td class="muted"><?= htmlspecialchars($r['staff_no']) ?></td>
                    <td class="muted"><?= htmlspecialchars($r['job_title']) ?></td>
                    <td class="muted"><?= htmlspecialchars($r['workstation']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endforeach; ?>

    <?php endif; ?>

    </div><!-- /doc-body -->

    <div class="doc-footer">
        <div><strong>MOHI Learning &amp; Development</strong> &nbsp;·&nbsp; <?= htmlspecialchars($reportTitle) ?></div>
        <div>Generated: <?= $generatedAt ?> &nbsp;·&nbsp; Confidential</div>
    </div>

</div><!-- /page -->
<script>
// ── Share helpers ──────────────────────────────────────────────────
const pageTitle = document.title;
const pageUrl   = window.location.href;

// Show native share button only if supported (mobile)
if (navigator.share) {
    document.getElementById('nativeShareBtn').style.display = 'flex';
}

function toggleShare(e) {
    e.stopPropagation();
    document.getElementById('shareMenu').classList.toggle('open');
}

// Close menu when clicking outside
document.addEventListener('click', () => {
    document.getElementById('shareMenu').classList.remove('open');
});

function nativeShare() {
    navigator.share({ title: pageTitle, url: pageUrl })
        .catch(() => {});
}

function shareLinkedIn() {
    const url = 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(pageUrl);
    window.open(url, '_blank', 'width=600,height=500');
}

function shareWhatsApp(e) {
    e.preventDefault();
    const msg = pageTitle + ' — ' + pageUrl;
    window.open('https://wa.me/?text=' + encodeURIComponent(msg), '_blank');
}

function shareTwitter(e) {
    e.preventDefault();
    const msg = 'MOHI L&D Report: ' + pageTitle;
    window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent(msg) + '&url=' + encodeURIComponent(pageUrl), '_blank', 'width=600,height=400');
}

function shareEmail(e) {
    e.preventDefault();
    const subject = encodeURIComponent('MOHI L&D Report: ' + pageTitle);
    const body    = encodeURIComponent('Please find the report at the following link:\n\n' + pageUrl);
    window.location.href = 'mailto:?subject=' + subject + '&body=' + body;
}

function copyLink() {
    navigator.clipboard.writeText(pageUrl).then(() => {
        const el = document.getElementById('copyLinkTxt');
        el.textContent = 'Copied!';
        setTimeout(() => el.textContent = 'Copy Report Link', 2000);
    });
}
</script>
</body>
</html>