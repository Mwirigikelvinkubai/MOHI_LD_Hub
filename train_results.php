<?php
/**
 * train_results.php — Training Results Management
 * Features:
 *   - View all results with search/sort
 *   - Add individual result (lookup by Email — unique identifier)
 *   - Batch upload results via CSV (Email as identifier)
 *   - Delete individual result
 *
 * CSV Format for batch upload:
 *   Email, Sub-Course Name, Score, Date (YYYY-MM-DD)
 *   (Training is selected at upload time)
 */

require_once 'config.php';
require_once 'auth.php';
requireAuth();

$pageTitle  = 'Results';
$activePage = 'train_results';

$pdo = getDB();

// ==================== POST HANDLERS ====================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ADD INDIVIDUAL RESULT — look up staff by email (unique identifier)
    if ($_POST['action'] === 'add_result') {
        $staff = $pdo->prepare("SELECT id FROM staff WHERE LOWER(email) = LOWER(?)");
        $staff->execute([clean($_POST['email'])]);
        $staffRow = $staff->fetch();

        if (!$staffRow) {
            setFlash('danger', 'Email "' . clean($_POST['email']) . '" not found in the staff directory.');
            redirect('train_results.php');
        }

        $stmt = $pdo->prepare("
            INSERT OR REPLACE INTO results (staff_id, training_id, sub_course_id, score, date_taken)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $staffRow['id'],
            (int)$_POST['training_id'],
            (int)$_POST['sub_course_id'],
            (float)$_POST['score'],
            clean($_POST['date_taken']),
        ]);
        setFlash('success', 'Result saved.');
        redirect('train_results.php');
    }

    // DELETE RESULT
    if ($_POST['action'] === 'delete_result') {
        $pdo->prepare("DELETE FROM results WHERE id=?")->execute([(int)$_POST['id']]);
        setFlash('success', 'Result deleted.');
        redirect('train_results.php');
    }

    // BATCH CSV UPLOAD — email is the unique staff identifier
    if ($_POST['action'] === 'batch_upload' && isset($_FILES['results_csv'])) {

        $training_id = (int)$_POST['batch_training_id'];

        // Verify training exists
        $training = $pdo->prepare("SELECT id, name FROM trainings WHERE id = ?");
        $training->execute([$training_id]);
        if (!$training->fetch()) {
            setFlash('danger', 'Invalid training selected.');
            redirect('train_results.php');
        }

        $file = $_FILES['results_csv'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            setFlash('danger', 'File upload error.');
            redirect('train_results.php');
        }

        $handle = fopen($file['tmp_name'], 'r');
        $inserted = $skipped = 0;
        $errors   = [];
        $rowNum   = 0;

        // Pre-load all sub-courses for this training (lowercase key, case-insensitive match)
        $scStmt = $pdo->prepare("SELECT id, name FROM sub_courses WHERE training_id = ?");
        $scStmt->execute([$training_id]);
        $subCoursesLookup = [];
        foreach ($scStmt->fetchAll() as $sc) {
            $subCoursesLookup[strtolower(trim($sc['name']))] = $sc['id'];
        }

        // Pre-load all staff keyed by lowercase EMAIL (the single unique identifier)
        $rawStaff = $pdo->query("SELECT LOWER(email) as email_key, id FROM staff")->fetchAll();
        $allStaff = [];
        foreach ($rawStaff as $s) {
            $allStaff[$s['email_key']] = $s['id'];
        }

        $stmt = $pdo->prepare("
            INSERT OR REPLACE INTO results (staff_id, training_id, sub_course_id, score, date_taken)
            VALUES (?, ?, ?, ?, ?)
        ");

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if ($rowNum === 1) continue; // Skip header row
            if (empty(array_filter($row))) continue;

            $row = array_pad($row, 4, '');
            [$email, $sc_name, $score, $date] = array_map('trim', $row);

            // Normalise email to lowercase for matching
            $emailKey = strtolower($email);

            // Validate — email must exist in staff table
            if (!isset($allStaff[$emailKey])) {
                $errors[] = "Row $rowNum: Email '$email' not found in staff directory.";
                $skipped++;
                continue;
            }

            $staffId = $allStaff[$emailKey];

            // Find sub-course (case-insensitive)
            $scKey = strtolower(trim($sc_name));
            if (!isset($subCoursesLookup[$scKey])) {
                $errors[] = "Row $rowNum: Sub-course '$sc_name' not found in this training.";
                $skipped++;
                continue;
            }

            // Validate score
            if (!is_numeric($score) || $score < 0 || $score > 100) {
                $errors[] = "Row $rowNum: Invalid score '$score' (must be 0–100).";
                $skipped++;
                continue;
            }

            $stmt->execute([
                $staffId,
                $training_id,
                $subCoursesLookup[$scKey],
                (float)$score,
                $date ?: null,
            ]);
            $inserted++;
        }

        fclose($handle);

        $msg = "Batch upload done: $inserted results saved, $skipped skipped.";
        if (!empty($errors)) {
            $msg .= ' Errors: ' . implode(' | ', array_slice($errors, 0, 5));
            if (count($errors) > 5) $msg .= ' (+ ' . (count($errors) - 5) . ' more)';
        }
        setFlash($skipped > 0 ? 'danger' : 'success', $msg);
        redirect('train_results.php');
    }
}

// ==================== FETCH DATA ====================

// Fetch trainings for dropdowns
$trainings = $pdo->query("SELECT * FROM trainings ORDER BY name")->fetchAll();

// Fetch sub_courses per training for JS
$allSC = $pdo->query("SELECT id, training_id, name, pass_mark FROM sub_courses ORDER BY name")->fetchAll();
$scByTraining = [];
foreach ($allSC as $sc) {
    $scByTraining[$sc['training_id']][] = $sc;
}

// All results with joined data — include email in select
$results = $pdo->query("
    SELECT r.id, r.score, r.date_taken,
           s.staff_no, s.full_name, s.email, s.department, s.workstation,
           t.name as training_name,
           sc.name as sub_course_name, sc.pass_mark
    FROM results r
    JOIN staff s      ON s.id = r.staff_id
    JOIN trainings t  ON t.id = r.training_id
    JOIN sub_courses sc ON sc.id = r.sub_course_id
    ORDER BY r.created_at DESC
")->fetchAll();

require 'header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <div class="section-title">Training Results</div>
        <div class="section-sub"><?= number_format(count($results)) ?> results logged</div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn-ghost" data-bs-toggle="modal" data-bs-target="#batchModal">
            <i class="bi bi-cloud-upload"></i> Batch Upload CSV
        </button>
        <button class="btn-accent" data-bs-toggle="modal" data-bs-target="#addResultModal">
            <i class="bi bi-plus-lg"></i> Add Result
        </button>
    </div>
</div>

<!-- CSV hint -->
<div class="alert alert-info" style="font-size:12px;">
    <i class="bi bi-info-circle"></i>
    <strong>Batch CSV Format:</strong> Email | Sub-Course Name | Score (0–100) | Date (YYYY-MM-DD)
    &nbsp;—&nbsp; Select the Training when uploading. Email must match the staff directory exactly. Sub-course names are case-insensitive.
</div>

<!-- Results Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table datatable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Email</th>
                    <th>Full Name</th>
                    <th>Department</th>
                    <th>Training</th>
                    <th>Sub-Course</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $r): ?>
                <tr>
                    <td class="text-muted-c"><?= htmlspecialchars($r['date_taken'] ?? '—') ?></td>
                    <td>
                        <span class="text-accent" style="font-size:12px"><?= htmlspecialchars($r['email']) ?></span>
                        <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($r['staff_no']) ?></div>
                    </td>
                    <td><strong><?= htmlspecialchars($r['full_name']) ?></strong></td>
                    <td class="text-muted-c"><?= htmlspecialchars($r['department']) ?></td>
                    <td><?= htmlspecialchars($r['training_name']) ?></td>
                    <td class="text-muted-c"><?= htmlspecialchars($r['sub_course_name']) ?></td>
                    <td>
                        <strong><?= $r['score'] ?>%</strong>
                        <div class="score-bar-wrap mt-1">
                            <div class="score-bar <?= $r['score'] < 50 ? 'low' : ($r['score'] < 70 ? 'mid' : '') ?>"
                                 style="width:<?= min($r['score'],100) ?>%"></div>
                        </div>
                    </td>
                    <td>
                        <?php if ($r['score'] >= $r['pass_mark']): ?>
                            <span class="badge-pass">PASS</span>
                        <?php else: ?>
                            <span class="badge-fail">FAIL</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this result?')">
                            <input type="hidden" name="action" value="delete_result">
                            <input type="hidden" name="id"     value="<?= $r['id'] ?>">
                            <button type="submit" class="btn-danger-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    <a href="train_export.php?type=results" class="btn-ghost"><i class="bi bi-download"></i> Export Results CSV</a>
</div>


<!-- ===== ADD INDIVIDUAL RESULT MODAL ===== -->
<div class="modal fade" id="addResultModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="add_result">
        <div class="modal-header">
            <h5 class="modal-title">Add Individual Result</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Staff Email *</label>
                    <input type="email" name="email" class="form-control" required placeholder="e.g. john.doe@mohi.org">
                    <div style="font-size:11px;color:var(--muted);margin-top:4px">
                        Email is the unique staff identifier — must match the staff directory exactly
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Training *</label>
                    <select name="training_id" id="modal_training_id" class="form-select" required onchange="loadSubCourses(this.value)">
                        <option value="">— Select Training —</option>
                        <?php foreach ($trainings as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Sub-Course *</label>
                    <select name="sub_course_id" id="modal_sc_id" class="form-select" required>
                        <option value="">— Select Training first —</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label">Score (%) *</label>
                    <input type="number" name="score" class="form-control" min="0" max="100" step="0.1" required placeholder="e.g. 78.5">
                </div>
                <div class="col-6">
                    <label class="form-label">Date Taken</label>
                    <input type="date" name="date_taken" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-accent"><i class="bi bi-check-lg"></i> Save Result</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- ===== BATCH UPLOAD MODAL ===== -->
<div class="modal fade" id="batchModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="batch_upload">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-cloud-upload me-2"></i>Batch Upload Results</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <p style="font-size:13px;color:var(--muted);margin-bottom:16px;">
                Select the training, then upload a CSV with columns:<br>
                <code style="color:var(--accent)">Email, Sub-Course Name, Score, Date</code><br><br>
                <strong style="color:var(--text2)">Email</strong> is the unique staff identifier and must match
                the staff directory exactly (case-insensitive). Sub-course names are also matched
                case-insensitively. Existing results for the same staff + sub-course will be updated.
            </p>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Training *</label>
                    <select name="batch_training_id" class="form-select" required>
                        <option value="">— Select Training —</option>
                        <?php foreach ($trainings as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">CSV File *</label>
                    <input type="file" name="results_csv" class="form-control" accept=".csv" required>
                    <div style="font-size:11px;color:var(--muted);margin-top:5px">
                        Row 1 = header (skipped). Columns: <code>Email, Sub-Course Name, Score, Date</code>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-accent"><i class="bi bi-cloud-upload"></i> Upload &amp; Process</button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
// Sub-courses data (passed from PHP to JS)
const subCourses = <?= json_encode($scByTraining) ?>;

// When user selects a training in the add modal, populate sub-course dropdown
function loadSubCourses(trainingId) {
    const select = document.getElementById('modal_sc_id');
    select.innerHTML = '<option value="">— Select Sub-Course —</option>';

    const scs = subCourses[trainingId] || [];
    if (scs.length === 0) {
        select.innerHTML = '<option value="">No sub-courses found for this training</option>';
        return;
    }

    scs.forEach(sc => {
        const opt = document.createElement('option');
        opt.value = sc.id;
        opt.textContent = sc.name + ' (Pass: ' + sc.pass_mark + '%)';
        select.appendChild(opt);
    });
}
</script>

<?php require 'footer.php'; ?>
