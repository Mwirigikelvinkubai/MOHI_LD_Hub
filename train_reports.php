<?php
/**
 * train_reports.php — Reports & Analytics
 * Multiple report views:
 *   1. Staff Training Completion — who has done which training
 *   2. Training Detail — all results for a specific training
 *   3. Staff Transcript — all results for a specific staff member (lookup by Email)
 *   4. Department Summary — pass rates by department
 *   5. Non-Participants — staff who haven't done a training
 *
 * Filters: Training | Department | Workstation | Staff Email (for transcript)
 */

require_once 'config.php';

$pageTitle  = 'Reports';
$activePage = 'train_reports';

$pdo = getDB();

// Get filter values from GET params
$reportType  = $_GET['report']       ?? 'completion';
$trainingId  = (int)($_GET['training_id'] ?? 0);
$staffEmail  = clean($_GET['staff_email'] ?? '');   // email — unique identifier
$department  = clean($_GET['dept']        ?? '');
$workstation = clean($_GET['workstation'] ?? '');   // new workstation filter

// Dropdown data
$trainings    = $pdo->query("SELECT * FROM trainings ORDER BY name")->fetchAll();
$departments  = $pdo->query("SELECT DISTINCT department  FROM staff WHERE department  != '' ORDER BY department" )->fetchAll(PDO::FETCH_COLUMN);
$workstations = $pdo->query("SELECT DISTINCT workstation FROM staff WHERE workstation != '' ORDER BY workstation")->fetchAll(PDO::FETCH_COLUMN);

$reportData  = [];
$reportTitle = '';

// ==================== REPORT LOGIC ====================

// ------ 1. STAFF COMPLETION OVERVIEW ------
if ($reportType === 'completion') {
    $reportTitle = 'Staff Training Completion Overview';

    $where  = "WHERE 1=1";
    $params = [];
    if ($department)  { $where .= " AND s.department  = ?"; $params[] = $department; }
    if ($workstation) { $where .= " AND s.workstation = ?"; $params[] = $workstation; }
    if ($trainingId)  { $where .= " AND t.id = ?";          $params[] = $trainingId; }

    $stmt = $pdo->prepare("
        SELECT s.staff_no, s.full_name, s.email, s.department, s.workstation,
               t.name as training_name, t.id as training_id,
               COUNT(r.id) as sub_courses_done,
               (SELECT COUNT(*) FROM sub_courses WHERE training_id = t.id) as sub_courses_total,
               ROUND(AVG(r.score), 1) as avg_score,
               SUM(CASE WHEN r.score >= sc.pass_mark THEN 1 ELSE 0 END) as passed,
               SUM(CASE WHEN r.score < sc.pass_mark  THEN 1 ELSE 0 END) as failed
        FROM staff s
        CROSS JOIN trainings t
        LEFT JOIN results r ON r.staff_id = s.id AND r.training_id = t.id
        LEFT JOIN sub_courses sc ON sc.id = r.sub_course_id
        $where
        GROUP BY s.id, t.id
        ORDER BY s.full_name, t.name
    ");
    $stmt->execute($params);
    $reportData = $stmt->fetchAll();
}

// ------ 2. TRAINING DETAIL REPORT ------
if ($reportType === 'training_detail' && $trainingId) {
    $trainingRow = $pdo->prepare("SELECT * FROM trainings WHERE id = ?");
    $trainingRow->execute([$trainingId]);
    $trainingRow = $trainingRow->fetch();
    $reportTitle = 'Training Detail: ' . ($trainingRow['name'] ?? '');

    $where  = "WHERE r.training_id = ?";
    $params = [$trainingId];
    if ($department)  { $where .= " AND s.department  = ?"; $params[] = $department; }
    if ($workstation) { $where .= " AND s.workstation = ?"; $params[] = $workstation; }

    $stmt = $pdo->prepare("
        SELECT s.staff_no, s.full_name, s.email, s.department, s.workstation,
               sc.name as sub_course, sc.pass_mark, r.score, r.date_taken,
               CASE WHEN r.score >= sc.pass_mark THEN 'Pass' ELSE 'Fail' END as status
        FROM results r
        JOIN staff s      ON s.id = r.staff_id
        JOIN sub_courses sc ON sc.id = r.sub_course_id
        $where
        ORDER BY s.full_name, sc.name
    ");
    $stmt->execute($params);
    $reportData = $stmt->fetchAll();
}

// ------ 3. STAFF TRANSCRIPT (lookup by email) ------
if ($reportType === 'transcript' && $staffEmail) {
    $staffRow = $pdo->prepare("SELECT * FROM staff WHERE LOWER(email) = LOWER(?)");
    $staffRow->execute([$staffEmail]);
    $staffRow = $staffRow->fetch();

    if ($staffRow) {
        $reportTitle = 'Transcript: ' . $staffRow['full_name']
                     . ' &nbsp;<span style="font-size:12px;font-weight:400;color:var(--muted)">'
                     . htmlspecialchars($staffRow['email']) . '</span>';

        $stmt = $pdo->prepare("
            SELECT t.name as training, sc.name as sub_course, sc.pass_mark,
                   r.score, r.date_taken,
                   CASE WHEN r.score >= sc.pass_mark THEN 'Pass' ELSE 'Fail' END as status
            FROM results r
            JOIN trainings t    ON t.id = r.training_id
            JOIN sub_courses sc ON sc.id = r.sub_course_id
            WHERE r.staff_id = ?
            ORDER BY t.name, sc.name
        ");
        $stmt->execute([$staffRow['id']]);
        $reportData = $stmt->fetchAll();
    } else {
        $reportTitle = 'Transcript';
        $reportData  = [];
        $emailNotFound = true;
    }
}

// ------ 4. DEPARTMENT SUMMARY — Training + Sub-course Matrix ------
if ($reportType === 'dept_summary') {
    $reportTitle = 'Department Training Matrix';

    // Get trainings (filtered or all)
    if ($trainingId) {
        $matrixTrainings = $pdo->prepare("SELECT id, name FROM trainings WHERE id = ?");
        $matrixTrainings->execute([$trainingId]);
        $matrixTrainings = $matrixTrainings->fetchAll();
    } else {
        $matrixTrainings = $pdo->query("SELECT id, name FROM trainings ORDER BY name")->fetchAll();
    }

    // Get sub-courses per training: [training_id => [{id, name}, ...]]
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

    // Get staff filtered by dept/workstation
    $swhere = "WHERE 1=1"; $sparams = [];
    if ($department)  { $swhere .= " AND s.department  = ?"; $sparams[] = $department; }
    if ($workstation) { $swhere .= " AND s.workstation = ?"; $sparams[] = $workstation; }
    $staffStmt = $pdo->prepare("
        SELECT s.id, s.full_name, s.email, s.department, s.workstation
        FROM staff s $swhere ORDER BY s.department, s.full_name
    ");
    $staffStmt->execute($sparams);
    $matrixStaff = $staffStmt->fetchAll();

    // Completion map: staff_id => sub_course_id => true
    $doneMap = [];
    foreach ($pdo->query("SELECT DISTINCT staff_id, sub_course_id FROM results")->fetchAll() as $row) {
        $doneMap[$row['staff_id']][$row['sub_course_id']] = true;
    }

    $reportData = $matrixStaff;
}

// ------ 5. NON-PARTICIPANTS ------
if ($reportType === 'non_participants' && $trainingId) {
    $tName = $pdo->prepare("SELECT name FROM trainings WHERE id=?");
    $tName->execute([$trainingId]);
    $tName = $tName->fetchColumn();
    $reportTitle = "Non-Participants: $tName";

    $where  = "WHERE 1=1";
    $params = [];
    if ($department)  { $where .= " AND s.department  = ?"; $params[] = $department; }
    if ($workstation) { $where .= " AND s.workstation = ?"; $params[] = $workstation; }

    $stmt = $pdo->prepare("
        SELECT s.staff_no, s.full_name, s.job_title, s.department, s.workstation, s.email
        FROM staff s
        $where
        AND s.id NOT IN (
            SELECT DISTINCT staff_id FROM results WHERE training_id = ?
        )
        ORDER BY s.department, s.full_name
    ");
    $params[] = $trainingId;
    $stmt->execute($params);
    $reportData = $stmt->fetchAll();
}

// Build export URL from current GET params
$exportUrl = 'train_export.php?' . http_build_query(array_merge($_GET, ['type' => 'report']));

require 'header.php';
?>

<div class="section-title mb-1">Reports</div>
<div class="section-sub mb-4">Pull, filter, and export data across all aspects of your training programme</div>

<!-- FILTER PANEL -->
<div class="card mb-4">
    <form method="GET" action="train_reports.php" class="row g-3 align-items-end">

        <!-- Report type -->
        <div class="col-md-3">
            <label class="form-label">Report Type</label>
            <select name="report" class="form-select" onchange="this.form.submit()">
                <option value="completion"       <?= $reportType==='completion'       ? 'selected':'' ?>>Staff Completion Overview</option>
                <option value="training_detail"  <?= $reportType==='training_detail'  ? 'selected':'' ?>>Training Detail</option>
                <option value="transcript"       <?= $reportType==='transcript'       ? 'selected':'' ?>>Staff Transcript</option>
                <option value="dept_summary"     <?= $reportType==='dept_summary'     ? 'selected':'' ?>>Department Summary</option>
                <option value="non_participants" <?= $reportType==='non_participants' ? 'selected':'' ?>>Non-Participants</option>
            </select>
        </div>

        <!-- Training filter -->
        <?php if (in_array($reportType, ['completion','training_detail','dept_summary','non_participants'])): ?>
        <div class="col-md-2">
            <label class="form-label">Training</label>
            <select name="training_id" class="form-select">
                <option value="">— All —</option>
                <?php foreach ($trainings as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $trainingId==$t['id'] ? 'selected':'' ?>>
                    <?= htmlspecialchars($t['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- Email filter for transcript -->
        <?php if ($reportType === 'transcript'): ?>
        <div class="col-md-4">
            <label class="form-label">Staff Email *</label>
            <input type="email" name="staff_email" class="form-control"
                   value="<?= htmlspecialchars($staffEmail) ?>"
                   placeholder="e.g. john.doe@mohi.org">
            <div style="font-size:11px;color:var(--muted);margin-top:3px">Email is the unique staff identifier</div>
        </div>
        <?php endif; ?>

        <!-- Department filter -->
        <?php if (in_array($reportType, ['completion','training_detail','dept_summary','non_participants'])): ?>
        <div class="col-md-2">
            <label class="form-label">Department</label>
            <select name="dept" class="form-select">
                <option value="">— All —</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>" <?= $department===$d ? 'selected':'' ?>>
                    <?= htmlspecialchars($d) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- Workstation filter — all report types -->
        <?php if ($reportType !== 'transcript'): ?>
        <div class="col-md-2">
            <label class="form-label">Work Station</label>
            <select name="workstation" class="form-select">
                <option value="">— All —</option>
                <?php foreach ($workstations as $w): ?>
                <option value="<?= htmlspecialchars($w) ?>" <?= $workstation===$w ? 'selected':'' ?>>
                    <?= htmlspecialchars($w) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="col-md-auto d-flex gap-2">
            <button type="submit" class="btn-accent"><i class="bi bi-funnel"></i> Run Report</button>
            <a href="train_reports.php?report=<?= $reportType ?>" class="btn-ghost"><i class="bi bi-x"></i> Clear</a>
        </div>
    </form>
</div>

<!-- REPORT RESULTS -->
<div class="card">
    <div class="card-header-custom">
        <h5><?= $reportTitle ?: 'Results' ?></h5>
        <?php if (!empty($reportData)): ?>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <span class="text-muted-c" style="font-size:12px"><?= count($reportData) ?> rows</span>
            <a href="<?= $exportUrl ?>" class="btn-ghost"><i class="bi bi-download"></i> Export CSV</a>
            <?php if ($reportType === 'transcript' && $staffEmail): ?>
            <a href="transcript_download.php?email=<?= urlencode($staffEmail) ?>"
               target="_blank" class="btn-accent" style="font-size:13px;padding:6px 14px;">
                <i class="bi bi-file-earmark-person"></i> Download Transcript
            </a>
            <?php elseif (in_array($reportType, ['completion','training_detail','dept_summary','non_participants'])): ?>
            <a href="report_download.php?<?= http_build_query(array_filter([
                    'report'      => $reportType,
                    'training_id' => $trainingId ?: '',
                    'dept'        => $department,
                    'workstation' => $workstation,
                ])) ?>"
               target="_blank" class="btn-accent" style="font-size:13px;padding:6px 14px;">
                <i class="bi bi-file-earmark-bar-graph"></i> Download Report
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($emailNotFound)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle"></i>
            Email <strong><?= htmlspecialchars($staffEmail) ?></strong> was not found in the staff directory.
            Please check the address and try again.
        </div>

    <?php elseif (empty($reportData) && $reportType === 'transcript' && !$staffEmail): ?>
        <p class="text-muted-c" style="font-size:13px">
            Enter a staff email address above and click <strong>Run Report</strong> to view their transcript.
        </p>

    <?php elseif (empty($reportData)): ?>
        <p class="text-muted-c" style="font-size:13px">No data to display. Adjust filters and run the report.</p>

    <?php elseif ($reportType === 'completion'): ?>
        <div class="table-responsive">
        <table class="table datatable">
            <thead><tr>
                <th>Staff No</th><th>Full Name</th><th>Email</th><th>Department</th><th>Work Station</th>
                <th>Training</th><th>Completion</th><th>Avg Score</th><th>Passed</th><th>Failed</th>
            </tr></thead>
            <tbody>
            <?php foreach ($reportData as $r): ?>
                <?php $pct = $r['sub_courses_total'] > 0 ? round(($r['sub_courses_done'] / $r['sub_courses_total']) * 100) : 0; ?>
                <tr>
                    <td class="text-accent"><?= htmlspecialchars($r['staff_no']) ?></td>
                    <td><strong><?= htmlspecialchars($r['full_name']) ?></strong></td>
                    <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($r['email']) ?></td>
                    <td><span class="dept-pill"><?= htmlspecialchars($r['department']) ?></span></td>
                    <td class="text-muted-c"><?= htmlspecialchars($r['workstation']) ?></td>
                    <td><?= htmlspecialchars($r['training_name']) ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <div class="score-bar-wrap" style="min-width:60px">
                                <div class="score-bar" style="width:<?= $pct ?>%"></div>
                            </div>
                            <span style="font-size:12px;color:var(--muted)"><?= $r['sub_courses_done'] ?>/<?= $r['sub_courses_total'] ?></span>
                        </div>
                    </td>
                    <td><?= $r['avg_score'] ?? '—' ?>%</td>
                    <td><span class="badge-pass"><?= (int)$r['passed'] ?></span></td>
                    <td><?= (int)$r['failed'] > 0 ? '<span class="badge-fail">'.(int)$r['failed'].'</span>' : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

    <?php elseif ($reportType === 'training_detail'): ?>
        <div class="table-responsive">
        <table class="table datatable">
            <thead><tr>
                <th>Staff No</th><th>Full Name</th><th>Email</th><th>Department</th><th>Work Station</th>
                <th>Sub-Course</th><th>Score</th><th>Pass Mark</th><th>Status</th><th>Date</th>
            </tr></thead>
            <tbody>
            <?php foreach ($reportData as $r): ?>
                <tr>
                    <td class="text-accent"><?= htmlspecialchars($r['staff_no']) ?></td>
                    <td><strong><?= htmlspecialchars($r['full_name']) ?></strong></td>
                    <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($r['email']) ?></td>
                    <td><span class="dept-pill"><?= htmlspecialchars($r['department']) ?></span></td>
                    <td class="text-muted-c"><?= htmlspecialchars($r['workstation']) ?></td>
                    <td><?= htmlspecialchars($r['sub_course']) ?></td>
                    <td><strong><?= $r['score'] ?>%</strong></td>
                    <td class="text-muted-c"><?= $r['pass_mark'] ?>%</td>
                    <td><?= $r['status'] === 'Pass' ? '<span class="badge-pass">PASS</span>' : '<span class="badge-fail">FAIL</span>' ?></td>
                    <td class="text-muted-c"><?= htmlspecialchars($r['date_taken'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

    <?php elseif ($reportType === 'transcript'): ?>
        <?php if ($staffRow ?? false): ?>
        <!-- Staff info banner -->
        <div style="display:flex;gap:16px;flex-wrap:wrap;padding:14px 0 18px;border-bottom:1px solid var(--border);margin-bottom:16px;font-size:13px;">
            <div><span class="text-muted-c">Staff No:</span> <strong><?= htmlspecialchars($staffRow['staff_no']) ?></strong></div>
            <div><span class="text-muted-c">Email:</span> <span class="text-accent"><?= htmlspecialchars($staffRow['email']) ?></span></div>
            <div><span class="text-muted-c">Department:</span> <span class="dept-pill"><?= htmlspecialchars($staffRow['department']) ?></span></div>
            <div><span class="text-muted-c">Work Station:</span> <?= htmlspecialchars($staffRow['workstation']) ?></div>
            <div><span class="text-muted-c">Job Title:</span> <?= htmlspecialchars($staffRow['job_title']) ?></div>
        </div>
        <?php endif; ?>
        <div class="table-responsive">
        <table class="table datatable">
            <thead><tr>
                <th>Training</th><th>Sub-Course</th><th>Score</th><th>Pass Mark</th><th>Status</th><th>Date</th>
            </tr></thead>
            <tbody>
            <?php foreach ($reportData as $r): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['training']) ?></strong></td>
                    <td class="text-muted-c"><?= htmlspecialchars($r['sub_course']) ?></td>
                    <td><strong><?= $r['score'] ?>%</strong></td>
                    <td class="text-muted-c"><?= $r['pass_mark'] ?>%</td>
                    <td><?= $r['status'] === 'Pass' ? '<span class="badge-pass">PASS</span>' : '<span class="badge-fail">FAIL</span>' ?></td>
                    <td class="text-muted-c"><?= htmlspecialchars($r['date_taken'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

    <?php elseif ($reportType === 'dept_summary'):
        $staffByDept = [];
        foreach ($matrixStaff as $s) {
            $staffByDept[$s['department'] ?: 'Unassigned'][] = $s;
        }
    ?>
        <!-- Legend -->
        <div style="display:flex;gap:16px;margin-bottom:14px;font-size:12px;align-items:center;flex-wrap:wrap;">
            <span style="color:var(--muted)">Legend:</span>
            <span style="color:#4a9e1e;font-weight:700;font-size:15px">✓</span><span style="color:var(--muted)"> Sub-course done</span>
            <span style="color:#c0392b;font-weight:700;font-size:15px">✗</span><span style="color:var(--muted)"> Not yet done</span>
        </div>

        <?php foreach ($staffByDept as $deptName => $deptStaff): ?>
        <div style="margin-bottom:28px">
            <div style="font-family:'Barlow',sans-serif;font-weight:700;color:var(--navy);font-size:13px;
                        padding:8px 14px;background:var(--surface2);border:1px solid var(--border);
                        border-radius:6px 6px 0 0;display:flex;justify-content:space-between;align-items:center;">
                <span><?= htmlspecialchars($deptName) ?></span>
                <span style="font-size:11px;font-weight:500;color:var(--muted)"><?= count($deptStaff) ?> staff</span>
            </div>
            <div class="table-responsive" style="border:1px solid var(--border);border-top:none;border-radius:0 0 6px 6px;overflow:auto">
            <table class="table" style="margin:0;font-size:11.5px;min-width:100%">
                <thead>
                    <!-- Row 1: Training group headers -->
                    <tr>
                        <th rowspan="2" style="min-width:150px;vertical-align:middle">Full Name</th>
                        <th rowspan="2" style="min-width:170px;vertical-align:middle">Email</th>
                        <?php foreach ($matrixTrainings as $tr):
                            $scCount = count($matrixSubCourses[$tr['id']] ?? []);
                            if ($scCount === 0) continue;
                        ?>
                        <th colspan="<?= $scCount ?>"
                            style="text-align:center;background:rgba(0,47,102,0.06);
                                   border-left:2px solid var(--border);font-size:10px;
                                   color:var(--navy);font-weight:700;padding:5px 8px;white-space:nowrap">
                            <?= htmlspecialchars($tr['name']) ?>
                            <span style="font-weight:400;color:var(--muted);margin-left:4px">(<?= $scCount ?> sub-courses)</span>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                    <!-- Row 2: Sub-course headers -->
                    <tr>
                        <?php foreach ($matrixTrainings as $tr):
                            foreach (($matrixSubCourses[$tr['id']] ?? []) as $sc):
                        ?>
                        <th style="text-align:center;font-size:9.5px;min-width:80px;max-width:100px;
                                   border-left:1px solid var(--border);line-height:1.3;
                                   white-space:normal;font-weight:600;color:var(--muted);
                                   background:var(--surface2);padding:4px 6px">
                            <?= htmlspecialchars($sc['name']) ?>
                        </th>
                        <?php endforeach; endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($deptStaff as $s):
                    // Count completed sub-courses for this staff across shown trainings
                    $scTotal = 0; $scDone = 0;
                    foreach ($matrixTrainings as $tr) {
                        foreach (($matrixSubCourses[$tr['id']] ?? []) as $sc) {
                            $scTotal++;
                            if (!empty($doneMap[$s['id']][$sc['id']])) $scDone++;
                        }
                    }
                ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($s['full_name']) ?></strong>
                            <div style="font-size:10px;color:var(--muted);margin-top:2px">
                                <?= $scDone ?>/<?= $scTotal ?> sub-courses
                            </div>
                        </td>
                        <td style="font-size:11px;color:var(--accent)"><?= htmlspecialchars($s['email']) ?></td>
                        <?php foreach ($matrixTrainings as $tr):
                            foreach (($matrixSubCourses[$tr['id']] ?? []) as $sc):
                        ?>
                        <td style="text-align:center;border-left:1px solid var(--border)">
                            <?php if (!empty($doneMap[$s['id']][$sc['id']])): ?>
                                <span style="color:#4a9e1e;font-size:16px;font-weight:700" title="Done">✓</span>
                            <?php else: ?>
                                <span style="color:#c0392b;font-size:16px;font-weight:700" title="Not done">✗</span>
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

    <?php elseif ($reportType === 'non_participants'): ?>
        <div class="table-responsive">
        <table class="table datatable">
            <thead><tr>
                <th>Staff No</th><th>Full Name</th><th>Job Title</th>
                <th>Department</th><th>Work Station</th><th>Email</th>
            </tr></thead>
            <tbody>
            <?php foreach ($reportData as $r): ?>
                <tr>
                    <td class="text-accent"><?= htmlspecialchars($r['staff_no']) ?></td>
                    <td><strong><?= htmlspecialchars($r['full_name']) ?></strong></td>
                    <td class="text-muted-c"><?= htmlspecialchars($r['job_title']) ?></td>
                    <td><span class="dept-pill"><?= htmlspecialchars($r['department']) ?></span></td>
                    <td class="text-muted-c"><?= htmlspecialchars($r['workstation']) ?></td>
                    <td class="text-muted-c"><?= htmlspecialchars($r['email']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>