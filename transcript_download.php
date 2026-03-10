<?php
/**
 * transcript_download.php — Printable Staff Transcript
 * Standalone page — no sidebar, clean MOHI-branded layout.
 * Opens in a new tab; user can Print → Save as PDF.
 * Called with ?email=staff@mohi.org
 */

require_once 'config.php';

$email = clean($_GET['email'] ?? '');

if (!$email) {
    die('<p style="font-family:sans-serif;padding:40px;color:#c00">No email provided. Go back and try again.</p>');
}

$pdo = getDB();

// Fetch staff record by email
$staffStmt = $pdo->prepare("SELECT * FROM staff WHERE LOWER(email) = LOWER(?)");
$staffStmt->execute([$email]);
$staff = $staffStmt->fetch();

if (!$staff) {
    die('<p style="font-family:sans-serif;padding:40px;color:#c00">Staff member with email <strong>' . htmlspecialchars($email) . '</strong> not found.</p>');
}

// Fetch all results grouped by training
$results = $pdo->prepare("
    SELECT t.name as training, t.start_date, t.end_date,
           sc.name as sub_course, sc.pass_mark,
           r.score, r.date_taken,
           CASE WHEN r.score >= sc.pass_mark THEN 'Pass' ELSE 'Fail' END as status
    FROM results r
    JOIN trainings t    ON t.id  = r.training_id
    JOIN sub_courses sc ON sc.id = r.sub_course_id
    WHERE r.staff_id = ?
    ORDER BY t.name, sc.name
");
$results->execute([$staff['id']]);
$allResults = $results->fetchAll();

// Group by training name
$byTraining = [];
foreach ($allResults as $row) {
    $byTraining[$row['training']][] = $row;
}

// Overall stats
$totalDone   = count($allResults);
$totalPassed = count(array_filter($allResults, fn($r) => $r['status'] === 'Pass'));
$overallRate = $totalDone > 0 ? round(($totalPassed / $totalDone) * 100) : 0;
$avgScore    = $totalDone > 0 ? round(array_sum(array_column($allResults, 'score')) / $totalDone, 1) : 0;

$generatedAt = date('d M Y, H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Transcript — <?= htmlspecialchars($staff['full_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        /* ── SCREEN STYLES ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Roboto', sans-serif;
            font-size: 13px;
            background: #f0f4f8;
            color: #1a2a3a;
            padding: 30px 20px;
        }

        .page {
            max-width: 820px;
            margin: 0 auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.12);
            overflow: hidden;
        }

        /* ── HEADER ── */
        .doc-header {
            background: #002F66;
            padding: 28px 36px 22px;
            position: relative;
            overflow: hidden;
        }

        .doc-header::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #26A9E0 0%, #8BC53F 100%);
        }

        .header-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .org-block { }

        .org-name {
            font-family: 'Barlow', sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: #fff;
            letter-spacing: 0.02em;
        }

        .org-tagline {
            font-size: 10px;
            color: #26A9E0;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-top: 3px;
        }

        .doc-type-block {
            text-align: right;
        }

        .doc-type {
            font-family: 'Barlow', sans-serif;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            color: rgba(255,255,255,0.5);
        }

        .doc-title {
            font-family: 'Barlow', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: #fff;
            margin-top: 2px;
        }

        /* ── STAFF INFO BAND ── */
        .staff-band {
            background: #003a7a;
            padding: 16px 36px;
            display: flex;
            gap: 32px;
            flex-wrap: wrap;
            align-items: center;
        }

        .staff-name {
            font-family: 'Barlow', sans-serif;
            font-size: 17px;
            font-weight: 700;
            color: #fff;
        }

        .staff-meta {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }

        .meta-item {
            font-size: 11.5px;
        }

        .meta-label {
            color: rgba(122,170,200,0.7);
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 700;
            font-family: 'Barlow', sans-serif;
            display: block;
            margin-bottom: 2px;
        }

        .meta-value {
            color: #e8f2ff;
            font-weight: 500;
        }

        /* ── SUMMARY STATS ── */
        .stats-row {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #e8eef4;
        }

        .stat-box {
            flex: 1;
            padding: 16px 20px;
            text-align: center;
            border-right: 1px solid #e8eef4;
        }

        .stat-box:last-child { border-right: none; }

        .stat-num {
            font-family: 'Barlow', sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: #002F66;
            line-height: 1;
        }

        .stat-num.pass  { color: #5a9e1e; }
        .stat-num.blue  { color: #0089BA; }
        .stat-num.gold  { color: #c48a00; }

        .stat-lbl {
            font-size: 10px;
            color: #7a94b0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-top: 4px;
            font-family: 'Barlow', sans-serif;
        }

        /* ── BODY ── */
        .doc-body {
            padding: 28px 36px;
        }

        /* Training section */
        .training-block {
            margin-bottom: 24px;
            border: 1px solid #dde6f0;
            border-radius: 8px;
            overflow: hidden;
        }

        .training-header {
            background: #f0f5fb;
            padding: 11px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #dde6f0;
            gap: 12px;
            flex-wrap: wrap;
        }

        .training-name {
            font-family: 'Barlow', sans-serif;
            font-size: 13.5px;
            font-weight: 700;
            color: #002F66;
        }

        .training-dates {
            font-size: 11px;
            color: #7a94b0;
        }

        .training-summary {
            display: flex;
            gap: 14px;
            font-size: 11px;
        }

        .t-stat { color: #7a94b0; }
        .t-stat strong { color: #1a2a3a; }

        /* Results table */
        table.results-tbl {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }

        table.results-tbl thead th {
            background: #f8fafc;
            color: #7a94b0;
            font-family: 'Barlow', sans-serif;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 8px 14px;
            text-align: left;
            border-bottom: 1px solid #dde6f0;
        }

        table.results-tbl tbody td {
            padding: 9px 14px;
            border-bottom: 1px solid #f0f4f8;
            vertical-align: middle;
        }

        table.results-tbl tbody tr:last-child td {
            border-bottom: none;
        }

        table.results-tbl tbody tr:hover td {
            background: #fafcff;
        }

        .score-cell {
            font-weight: 700;
            color: #002F66;
        }

        .badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            font-family: 'Barlow', sans-serif;
            letter-spacing: 0.04em;
        }

        .badge-pass {
            background: #eef7e2;
            color: #4a8a0e;
            border: 1px solid #c3e08a;
        }

        .badge-fail {
            background: #fef0f0;
            color: #c0392b;
            border: 1px solid #f5b7b1;
        }

        /* Score bar */
        .bar-wrap {
            display: inline-block;
            background: #e8eef4;
            border-radius: 20px;
            height: 5px;
            width: 60px;
            vertical-align: middle;
            margin-left: 6px;
        }

        .bar-fill {
            height: 5px;
            border-radius: 20px;
            background: linear-gradient(90deg, #26A9E0, #8BC53F);
        }

        .bar-fill.low { background: linear-gradient(90deg, #e74c3c, #f1948a); }
        .bar-fill.mid { background: linear-gradient(90deg, #f39c12, #f5d06a); }

        /* Empty state */
        .empty {
            text-align: center;
            padding: 40px 20px;
            color: #7a94b0;
            font-size: 13px;
        }

        /* Footer */
        .doc-footer {
            border-top: 1px solid #e8eef4;
            padding: 14px 36px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10.5px;
            color: #aabccc;
            flex-wrap: wrap;
            gap: 6px;
        }

        .doc-footer strong { color: #7a94b0; }

        /* Print button (screen only) */
        .print-bar {
            max-width: 820px;
            margin: 0 auto 18px;
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: flex-end;
        }

        .btn-print {
            background: linear-gradient(135deg, #26A9E0, #0089BA);
            color: #fff;
            border: none;
            border-radius: 7px;
            padding: 9px 20px;
            font-family: 'Barlow', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            box-shadow: 0 3px 12px rgba(38,169,224,0.3);
            text-decoration: none;
        }

        .btn-print:hover { background: linear-gradient(135deg, #259ACC, #26A9E0); }

        .btn-back {
            background: transparent;
            color: #7a94b0;
            border: 1px solid #ccdde8;
            border-radius: 7px;
            padding: 8px 16px;
            font-family: 'Barlow', sans-serif;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* ── PRINT STYLES ── */
        .share-wrap { position:relative; }
        .btn-share { background:#fff;color:#002F66;border:1.5px solid #ccdde8;border-radius:7px;padding:9px 18px;font-family:'Barlow',sans-serif;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:7px;box-shadow:0 2px 8px rgba(0,0,0,0.07); }
        .btn-share:hover { border-color:#26A9E0;color:#26A9E0; }
        .share-menu { display:none;position:absolute;right:0;top:calc(100% + 8px);background:#fff;border:1.5px solid #dde8f0;border-radius:10px;box-shadow:0 8px 32px rgba(0,47,102,0.15);min-width:220px;z-index:999;overflow:hidden; }
        .share-menu.open { display:block; }
        .share-hint { font-size:10.5px;color:#7a94b0;padding:10px 14px 6px;border-bottom:1px solid #f0f4f8;font-style:italic; }
        .share-item { display:flex;align-items:center;gap:10px;padding:10px 16px;font-size:13px;color:#1a2a3a;font-family:'Barlow',sans-serif;font-weight:500;text-decoration:none;background:none;border:none;width:100%;cursor:pointer;transition:background 0.15s; }
        .share-item:hover { background:#f4f8ff;color:#002F66; }
        .share-icon { width:26px;height:26px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;background:#f0f4f8;color:#1a2a3a; }
        .linkedin-icon { background:#0077b5;color:#fff;font-family:Georgia,serif;font-size:14px; }
        .wa-icon { background:#25d366;color:#fff; }
        .x-icon { background:#000;color:#fff;font-size:12px; }
        .email-icon { background:#26A9E0;color:#fff; }
        .share-note { font-size:10px;color:#7a94b0;padding:8px 14px 10px;border-top:1px solid #f0f4f8;line-height:1.5; }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .page {
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
            }

            .print-bar { display: none !important; }

            .training-block { break-inside: avoid; }

            @page {
                margin: 14mm 12mm;
                size: A4;
            }
        }
    </style>
</head>
<body>

<!-- Screen-only action bar -->
<div class="print-bar">
    <a href="javascript:history.back()" class="btn-back">← Back</a>

    <div class="share-wrap" id="shareWrap">
        <button class="btn-share" onclick="toggleShare(event)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            Share
        </button>
        <div class="share-menu" id="shareMenu">
            <div class="share-hint">Save as PDF first, then share the file</div>
            <button class="share-item" id="nativeShareBtn" onclick="nativeShare()" style="display:none">
                <span class="share-icon">📤</span> Share via Device
            </button>
            <a class="share-item" href="#" target="_blank" onclick="shareLinkedIn()">
                <span class="share-icon linkedin-icon">in</span> Share on LinkedIn
            </a>
            <a class="share-item" href="#" target="_blank" onclick="shareWhatsApp(event)">
                <span class="share-icon wa-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></span> Share on WhatsApp
            </a>
            <a class="share-item" href="#" target="_blank" onclick="shareTwitter(event)">
                <span class="share-icon x-icon">𝕏</span> Share on X / Twitter
            </a>
            <a class="share-item" href="#" onclick="shareEmail(event)">
                <span class="share-icon email-icon">✉</span> Share via Email
            </a>
            <button class="share-item" onclick="copyLink()">
                <span class="share-icon link-icon">🔗</span> <span id="copyLinkTxt">Copy Report Link</span>
            </button>
            <div class="share-note"><strong>Instagram / TikTok:</strong> Save as PDF, then upload the file directly in-app.</div>
        </div>
    </div>

    <button class="btn-print" onclick="window.print()">
        🖨 Print / Save as PDF
    </button>
</div>

<div class="page">

    <!-- Header -->
    <div class="doc-header">
        <div class="header-top">
            <div class="org-block">
                <div class="org-name">Missions of Hope International</div>
                <div class="org-tagline">Learning &amp; Development Department</div>
            </div>
            <div class="doc-type-block">
                <div class="doc-type">Official Document</div>
                <div class="doc-title">Training Transcript</div>
            </div>
        </div>
    </div>

    <!-- Staff info band -->
    <div class="staff-band">
        <div>
            <div class="staff-name"><?= htmlspecialchars($staff['full_name']) ?></div>
            <div style="font-size:11.5px;color:#7ab8d8;margin-top:2px"><?= htmlspecialchars($staff['email']) ?></div>
        </div>
        <div class="staff-meta">
            <div class="meta-item">
                <span class="meta-label">Staff No</span>
                <span class="meta-value"><?= htmlspecialchars($staff['staff_no']) ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Job Title</span>
                <span class="meta-value"><?= htmlspecialchars($staff['job_title'] ?: '—') ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Department</span>
                <span class="meta-value"><?= htmlspecialchars($staff['department'] ?: '—') ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Work Station</span>
                <span class="meta-value"><?= htmlspecialchars($staff['workstation'] ?: '—') ?></span>
            </div>
        </div>
    </div>

    <!-- Summary stats -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-num blue"><?= count($byTraining) ?></div>
            <div class="stat-lbl">Trainings</div>
        </div>
        <div class="stat-box">
            <div class="stat-num"><?= $totalDone ?></div>
            <div class="stat-lbl">Sub-Courses Taken</div>
        </div>
        <div class="stat-box">
            <div class="stat-num pass"><?= $totalPassed ?></div>
            <div class="stat-lbl">Passed</div>
        </div>
        <div class="stat-box">
            <div class="stat-num gold"><?= $avgScore ?>%</div>
            <div class="stat-lbl">Average Score</div>
        </div>
        <div class="stat-box">
            <div class="stat-num <?= $overallRate >= 70 ? 'pass' : ($overallRate >= 50 ? 'gold' : '') ?>"><?= $overallRate ?>%</div>
            <div class="stat-lbl">Pass Rate</div>
        </div>
    </div>

    <!-- Body -->
    <div class="doc-body">

        <?php if (empty($byTraining)): ?>
        <div class="empty">
            <div style="font-size:36px;margin-bottom:10px">📋</div>
            No training results recorded for this staff member yet.
        </div>

        <?php else: ?>

        <?php foreach ($byTraining as $trainingName => $rows): ?>
            <?php
                $tPassed  = count(array_filter($rows, fn($r) => $r['status'] === 'Pass'));
                $tTotal   = count($rows);
                $tAvg     = round(array_sum(array_column($rows, 'score')) / $tTotal, 1);
                $tDates   = array_filter(array_column($rows, 'date_taken'));
                $tDateStr = $tDates ? min($tDates) . ($tDates && max($tDates) !== min($tDates) ? ' – ' . max($tDates) : '') : '';
            ?>
            <div class="training-block">
                <div class="training-header">
                    <div>
                        <div class="training-name"><?= htmlspecialchars($trainingName) ?></div>
                        <?php if ($tDateStr): ?>
                        <div class="training-dates"><?= htmlspecialchars($tDateStr) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="training-summary">
                        <span class="t-stat"><strong><?= $tPassed ?>/<?= $tTotal ?></strong> passed</span>
                        <span class="t-stat">Avg <strong><?= $tAvg ?>%</strong></span>
                    </div>
                </div>

                <table class="results-tbl">
                    <thead>
                        <tr>
                            <th>Sub-Course</th>
                            <th>Score</th>
                            <th>Pass Mark</th>
                            <th>Status</th>
                            <th>Date Taken</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php
                            $barClass = $r['score'] < 50 ? 'low' : ($r['score'] < 70 ? 'mid' : '');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($r['sub_course']) ?></td>
                            <td class="score-cell">
                                <?= $r['score'] ?>%
                                <span class="bar-wrap">
                                    <span class="bar-fill <?= $barClass ?>" style="width:<?= min($r['score'],100) ?>%"></span>
                                </span>
                            </td>
                            <td style="color:#7a94b0"><?= $r['pass_mark'] ?>%</td>
                            <td>
                                <span class="badge <?= $r['status'] === 'Pass' ? 'badge-pass' : 'badge-fail' ?>">
                                    <?= $r['status'] ?>
                                </span>
                            </td>
                            <td style="color:#7a94b0"><?= htmlspecialchars($r['date_taken'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>

        <?php endif; ?>

    </div>

    <!-- Document footer -->
    <div class="doc-footer">
        <div>
            <strong>MOHI Learning &amp; Development</strong> &nbsp;·&nbsp; Official Training Record
        </div>
        <div>Generated: <?= $generatedAt ?> &nbsp;·&nbsp; Confidential</div>
    </div>

</div>

<script>
const pageTitle = document.title;
const pageUrl   = window.location.href;
if (navigator.share) document.getElementById('nativeShareBtn').style.display = 'flex';
function toggleShare(e) { e.stopPropagation(); document.getElementById('shareMenu').classList.toggle('open'); }
document.addEventListener('click', () => document.getElementById('shareMenu').classList.remove('open'));
function nativeShare() { navigator.share({ title: pageTitle, url: pageUrl }).catch(()=>{}); }
function shareLinkedIn() { window.open('https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(pageUrl), '_blank', 'width=600,height=500'); }
function shareWhatsApp(e) { e.preventDefault(); window.open('https://wa.me/?text=' + encodeURIComponent(pageTitle + ' — ' + pageUrl), '_blank'); }
function shareTwitter(e) { e.preventDefault(); window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent('MOHI L&D Report: ' + pageTitle) + '&url=' + encodeURIComponent(pageUrl), '_blank', 'width=600,height=400'); }
function shareEmail(e) { e.preventDefault(); window.location.href = 'mailto:?subject=' + encodeURIComponent('MOHI L&D: ' + pageTitle) + '&body=' + encodeURIComponent('Report link:\n\n' + pageUrl); }
function copyLink() { navigator.clipboard.writeText(pageUrl).then(() => { const el = document.getElementById('copyLinkTxt'); el.textContent = 'Copied!'; setTimeout(() => el.textContent = 'Copy Report Link', 2000); }); }
</script>
</body>
</html>