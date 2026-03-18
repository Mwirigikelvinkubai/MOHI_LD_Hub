<?php
/**
 * train_dashboard.php — Dashboard
 * Shows KPI stats and Chart.js visualizations of training data
 */

require_once 'config.php';

$pageTitle  = 'Dashboard';
$activePage = 'train_dashboard';

$pdo = getDB();

// ---- KPI Queries ----

// Total staff count
$totalStaff = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();

// Total trainings
$totalTrainings = $pdo->query("SELECT COUNT(*) FROM trainings")->fetchColumn();

// Total results logged
$totalResults = $pdo->query("SELECT COUNT(*) FROM results")->fetchColumn();

// Overall pass rate
$passData = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN r.score >= sc.pass_mark THEN 1 ELSE 0 END) as passed
    FROM results r
    JOIN sub_courses sc ON sc.id = r.sub_course_id
")->fetch();
$passRate = $passData['total'] > 0
    ? round(($passData['passed'] / $passData['total']) * 100, 1)
    : 0;

// ---- Chart Data: Staff by Department ----
$deptData = $pdo->query("
    SELECT department, COUNT(*) as cnt
    FROM staff
    WHERE department IS NOT NULL AND department != ''
    GROUP BY department
    ORDER BY cnt DESC
    LIMIT 10
")->fetchAll();

$deptLabels = array_column($deptData, 'department');
$deptCounts = array_column($deptData, 'cnt');

// ---- Chart Data: Pass vs Fail per Training ----
$trainingStats = $pdo->query("
    SELECT
        t.name,
        COUNT(r.id) as total,
        SUM(CASE WHEN r.score >= sc.pass_mark THEN 1 ELSE 0 END) as passed
    FROM trainings t
    LEFT JOIN results r ON r.training_id = t.id
    LEFT JOIN sub_courses sc ON sc.id = r.sub_course_id
    GROUP BY t.id
    ORDER BY t.created_at DESC
    LIMIT 8
")->fetchAll();

$trainNames    = array_column($trainingStats, 'name');
$trainPassed   = array_column($trainingStats, 'passed');
$trainFailed   = array_map(fn($r) => $r['total'] - $r['passed'], $trainingStats);

// ---- Chart Data: Average score per training ----
$avgScores = $pdo->query("
    SELECT t.name, ROUND(AVG(r.score), 1) as avg_score
    FROM trainings t
    JOIN results r ON r.training_id = t.id
    GROUP BY t.id
    ORDER BY avg_score DESC
    LIMIT 8
")->fetchAll();

$avgLabels = array_column($avgScores, 'name');
$avgValues = array_column($avgScores, 'avg_score');

// ---- Recent Activity: last 10 results ----
$recentResults = $pdo->query("
    SELECT r.date_taken, s.full_name, s.staff_no, t.name as training,
           sc.name as sub_course, r.score, sc.pass_mark
    FROM results r
    JOIN staff s      ON s.id = r.staff_id
    JOIN trainings t  ON t.id = r.training_id
    JOIN sub_courses sc ON sc.id = r.sub_course_id
    ORDER BY r.created_at DESC
    LIMIT 10
")->fetchAll();

// ---- Chart data: Score distribution buckets ----
$buckets = ['0-49' => 0, '50-59' => 0, '60-69' => 0, '70-79' => 0, '80-89' => 0, '90-100' => 0];
$allScores = $pdo->query("SELECT score FROM results")->fetchAll(PDO::FETCH_COLUMN);
foreach ($allScores as $s) {
    if ($s < 50)       $buckets['0-49']++;
    elseif ($s < 60)   $buckets['50-59']++;
    elseif ($s < 70)   $buckets['60-69']++;
    elseif ($s < 80)   $buckets['70-79']++;
    elseif ($s < 90)   $buckets['80-89']++;
    else               $buckets['90-100']++;
}

// JSON-encode chart data to pass into JavaScript
$jsData = [
    'deptLabels'  => $deptLabels,
    'deptCounts'  => $deptCounts,
    'trainNames'  => $trainNames,
    'trainPassed' => $trainPassed,
    'trainFailed' => $trainFailed,
    'avgLabels'   => $avgLabels,
    'avgValues'   => $avgValues,
    'bucketLabels'=> array_keys($buckets),
    'bucketCounts'=> array_values($buckets),
];

// Build JS block for charts
$extraScript = '<script>
const d = ' . json_encode($jsData) . ';

// Helper: truncate long names
const trunc = (s) => s.length > 18 ? s.slice(0,16) + "…" : s;

// 1. Staff by Department — Doughnut
new Chart(document.getElementById("chartDept"), {
    type: "doughnut",
    data: {
        labels: d.deptLabels,
        datasets: [{ data: d.deptCounts, backgroundColor: ["#10b981","#3b82f6","#f59e0b","#ef4444","#8b5cf6","#ec4899","#06b6d4","#14b8a6","#f97316","#a3e635"], borderWidth: 0, hoverOffset: 6 }]
    },
    options: { plugins: { legend: { position: "right", labels: { boxWidth: 12, font: { size: 11 }, color: "#7a94b0" } } }, cutout: "65%" }
});

// 2. Pass vs Fail per Training — Grouped bar
if (d.trainNames.length) {
    new Chart(document.getElementById("chartTraining"), {
        type: "bar",
        data: {
            labels: d.trainNames.map(trunc),
            datasets: [
                { label: "Passed", data: d.trainPassed, backgroundColor: "rgba(16,185,129,0.7)", borderRadius: 4 },
                { label: "Failed", data: d.trainFailed, backgroundColor: "rgba(239,68,68,0.6)", borderRadius: 4 }
            ]
        },
        options: { plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } }, scales: { x: { grid: { color: "#1e3050" } }, y: { grid: { color: "#1e3050" }, ticks: { stepSize: 1 } } } }
    });
}

// 3. Average score per training — Horizontal bar
if (d.avgLabels.length) {
    new Chart(document.getElementById("chartAvg"), {
        type: "bar",
        data: {
            labels: d.avgLabels.map(trunc),
            datasets: [{ label: "Avg Score", data: d.avgValues, backgroundColor: "rgba(59,130,246,0.7)", borderRadius: 4 }]
        },
        options: { indexAxis: "y", plugins: { legend: { display: false } }, scales: { x: { max: 100, grid: { color: "#1e3050" } }, y: { grid: { display: false } } } }
    });
}

// 4. Score distribution — Line/area
new Chart(document.getElementById("chartDist"), {
    type: "bar",
    data: {
        labels: d.bucketLabels,
        datasets: [{ label: "Count", data: d.bucketCounts, backgroundColor: "rgba(245,158,11,0.65)", borderRadius: 4 }]
    },
    options: { plugins: { legend: { display: false } }, scales: { x: { grid: { color: "#1e3050" } }, y: { grid: { color: "#1e3050" }, ticks: { stepSize: 1 } } } }
});
</script>';

require 'header.php';
?>

<!-- KPI STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($totalStaff) ?></div>
                <div class="stat-label">Total Staff</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-journal-bookmark-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($totalTrainings) ?></div>
                <div class="stat-label">Trainings Registered</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-clipboard2-check-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($totalResults) ?></div>
                <div class="stat-label">Results Logged</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon <?= $passRate >= 70 ? 'green' : ($passRate >= 50 ? 'amber' : 'red') ?>">
                <i class="bi bi-trophy-fill"></i>
            </div>
            <div>
                <div class="stat-value"><?= $passRate ?>%</div>
                <div class="stat-label">Overall Pass Rate</div>
            </div>
        </div>
    </div>
</div>

<!-- ROW 1: Dept chart + Training pass/fail chart -->
<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header-custom">
                <h5><i class="bi bi-pie-chart me-2 text-accent"></i>Staff by Department</h5>
            </div>
            <?php if (empty($deptLabels)): ?>
                <p class="text-muted-c" style="font-size:13px;">No staff data yet.</p>
            <?php else: ?>
                <canvas id="chartDept" height="220"></canvas>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header-custom">
                <h5><i class="bi bi-bar-chart me-2 text-accent"></i>Pass vs Fail by Training</h5>
            </div>
            <?php if (empty($trainNames)): ?>
                <p class="text-muted-c" style="font-size:13px;">No results logged yet.</p>
            <?php else: ?>
                <canvas id="chartTraining" height="200"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ROW 2: Avg score + Score distribution -->
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header-custom">
                <h5><i class="bi bi-speedometer2 me-2 text-accent"></i>Average Score per Training</h5>
            </div>
            <?php if (empty($avgLabels)): ?>
                <p class="text-muted-c" style="font-size:13px;">No results yet.</p>
            <?php else: ?>
                <canvas id="chartAvg" height="200"></canvas>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header-custom">
                <h5><i class="bi bi-distribute-horizontal me-2 text-accent"></i>Score Distribution</h5>
            </div>
            <?php if (empty($allScores)): ?>
                <p class="text-muted-c" style="font-size:13px;">No results yet.</p>
            <?php else: ?>
                <canvas id="chartDist" height="200"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="card">
    <div class="card-header-custom">
        <h5><i class="bi bi-clock-history me-2 text-accent"></i>Recent Results</h5>
        <a href="train_results.php" class="btn-ghost">View All</a>
    </div>
    <?php if (empty($recentResults)): ?>
        <p class="text-muted-c" style="font-size:13px;">No results logged yet. <a href="train_results.php" class="text-accent">Add results</a></p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Staff</th>
                        <th>Training</th>
                        <th>Sub-Course</th>
                        <th>Score</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentResults as $r): ?>
                    <tr>
                        <td class="text-muted-c"><?= htmlspecialchars($r['date_taken'] ?? '—') ?></td>
                        <td>
                            <strong><?= htmlspecialchars($r['full_name']) ?></strong>
                            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($r['staff_no']) ?></div>
                        </td>
                        <td><?= htmlspecialchars($r['training']) ?></td>
                        <td class="text-muted-c"><?= htmlspecialchars($r['sub_course']) ?></td>
                        <td>
                            <strong><?= $r['score'] ?>%</strong>
                            <div class="score-bar-wrap mt-1">
                                <div class="score-bar <?= $r['score'] < 50 ? 'low' : ($r['score'] < 70 ? 'mid' : '') ?>"
                                     style="width:<?= min($r['score'], 100) ?>%"></div>
                            </div>
                        </td>
                        <td>
                            <?php if ($r['score'] >= $r['pass_mark']): ?>
                                <span class="badge-pass">PASS</span>
                            <?php else: ?>
                                <span class="badge-fail">FAIL</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>