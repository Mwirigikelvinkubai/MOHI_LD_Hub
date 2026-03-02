<?php
/**
 * trainings.php — Training Programme Management
 * Features:
 *   - List all trainings with their sub-courses
 *   - Add new training
 *   - Add sub-courses to a training
 *   - Edit & delete trainings and sub-courses
 */

require_once 'config.php';

$pageTitle  = 'Trainings';
$activePage = 'train_trainings';

$pdo = getDB();

// ==================== POST HANDLERS ====================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ADD TRAINING
    if ($_POST['action'] === 'add_training') {
        $stmt = $pdo->prepare("INSERT INTO trainings (name, description, start_date, end_date) VALUES (?,?,?,?)");
        $stmt->execute([
            clean($_POST['name']),
            clean($_POST['description']),
            clean($_POST['start_date']),
            clean($_POST['end_date']),
        ]);
        setFlash('success', 'Training "' . clean($_POST['name']) . '" created.');
        redirect('train_trainings.php');
    }

    // EDIT TRAINING
    if ($_POST['action'] === 'edit_training') {
        $stmt = $pdo->prepare("UPDATE trainings SET name=?, description=?, start_date=?, end_date=? WHERE id=?");
        $stmt->execute([
            clean($_POST['name']),
            clean($_POST['description']),
            clean($_POST['start_date']),
            clean($_POST['end_date']),
            (int)$_POST['id'],
        ]);
        setFlash('success', 'Training updated.');
        redirect('train_trainings.php');
    }

    // DELETE TRAINING (cascades to sub_courses and results)
    if ($_POST['action'] === 'delete_training') {
        $pdo->prepare("DELETE FROM trainings WHERE id=?")->execute([(int)$_POST['id']]);
        setFlash('success', 'Training and all related data deleted.');
        redirect('train_trainings.php');
    }

    // ADD SUB-COURSE to a training
    if ($_POST['action'] === 'add_subcourse') {
        $stmt = $pdo->prepare("INSERT INTO sub_courses (training_id, name, pass_mark) VALUES (?,?,?)");
        $stmt->execute([
            (int)$_POST['training_id'],
            clean($_POST['sc_name']),
            (float)$_POST['pass_mark'],
        ]);
        setFlash('success', 'Sub-course added.');
        redirect('train_trainings.php#training-' . (int)$_POST['training_id']);
    }

    // DELETE SUB-COURSE
    if ($_POST['action'] === 'delete_subcourse') {
        $pdo->prepare("DELETE FROM sub_courses WHERE id=?")->execute([(int)$_POST['id']]);
        setFlash('success', 'Sub-course removed.');
        redirect('train_trainings.php');
    }
}

// ==================== FETCH DATA ====================

// All trainings
$trainings = $pdo->query("SELECT * FROM trainings ORDER BY created_at DESC")->fetchAll();

// All sub-courses (grouped by training_id)
$allSubCourses = $pdo->query("SELECT * FROM sub_courses ORDER BY id ASC")->fetchAll();

// Group sub-courses by training_id into an associative array
$subCoursesByTraining = [];
foreach ($allSubCourses as $sc) {
    $subCoursesByTraining[$sc['training_id']][] = $sc;
}

// Participant count per training (how many unique staff have results in it)
$participantCounts = $pdo->query("
    SELECT training_id, COUNT(DISTINCT staff_id) as cnt
    FROM results
    GROUP BY training_id
")->fetchAll(PDO::FETCH_KEY_PAIR);  // Returns [training_id => cnt]

require 'header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <div class="section-title">Training Programmes</div>
        <div class="section-sub"><?= count($trainings) ?> training(s) registered</div>
    </div>
    <button class="btn-accent" data-bs-toggle="modal" data-bs-target="#addTrainingModal">
        <i class="bi bi-plus-lg"></i> New Training
    </button>
</div>

<?php if (empty($trainings)): ?>
    <div class="card text-center" style="padding:48px">
        <div style="font-size:48px;margin-bottom:16px;">📋</div>
        <h5>No trainings yet</h5>
        <p class="text-muted-c">Click "New Training" to create your first training programme.</p>
    </div>
<?php else: ?>

<!-- TRAINING CARDS -->
<?php foreach ($trainings as $t): ?>
<div class="card mb-3" id="training-<?= $t['id'] ?>">
    <div class="card-header-custom">
        <div>
            <h5 style="font-size:16px"><?= htmlspecialchars($t['name']) ?></h5>
            <div style="font-size:12px;color:var(--muted);margin-top:4px">
                <?php if ($t['start_date']): ?>
                    <i class="bi bi-calendar3"></i>
                    <?= htmlspecialchars($t['start_date']) ?>
                    <?= $t['end_date'] ? ' → ' . htmlspecialchars($t['end_date']) : '' ?>
                    &nbsp;|&nbsp;
                <?php endif; ?>
                <i class="bi bi-people"></i>
                <?= $participantCounts[$t['id']] ?? 0 ?> participant(s)
                &nbsp;|&nbsp;
                <i class="bi bi-collection"></i>
                <?= count($subCoursesByTraining[$t['id']] ?? []) ?> sub-course(s)
            </div>
            <?php if ($t['description']): ?>
                <div style="font-size:13px;color:var(--muted);margin-top:6px"><?= htmlspecialchars($t['description']) ?></div>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button class="btn-edit-sm"
                onclick="openEditTraining(<?= htmlspecialchars(json_encode($t)) ?>)">
                <i class="bi bi-pencil"></i> Edit
            </button>
            <form method="POST" style="display:inline"
                  onsubmit="return confirm('Delete this training and ALL its results?')">
                <input type="hidden" name="action" value="delete_training">
                <input type="hidden" name="id"     value="<?= $t['id'] ?>">
                <button type="submit" class="btn-danger-sm"><i class="bi bi-trash"></i> Delete</button>
            </form>
        </div>
    </div>

    <!-- SUB-COURSES TABLE -->
    <div>
        <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);margin-bottom:10px">
            Sub-Courses / Modules
        </div>

        <?php $scs = $subCoursesByTraining[$t['id']] ?? []; ?>
        <?php if (empty($scs)): ?>
            <p style="font-size:13px;color:var(--muted)">No sub-courses yet. Add one below.</p>
        <?php else: ?>
            <table class="table mb-3" style="font-size:13px">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Sub-Course Name</th>
                        <th>Pass Mark</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scs as $i => $sc): ?>
                    <tr>
                        <td class="text-muted-c"><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($sc['name']) ?></td>
                        <td>
                            <span class="badge-pass"><?= $sc['pass_mark'] ?>%</span>
                        </td>
                        <td>
                            <form method="POST" style="display:inline"
                                  onsubmit="return confirm('Delete this sub-course? Its results will be lost.')">
                                <input type="hidden" name="action" value="delete_subcourse">
                                <input type="hidden" name="id"     value="<?= $sc['id'] ?>">
                                <button type="submit" class="btn-danger-sm"><i class="bi bi-x"></i> Remove</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Inline add sub-course form -->
        <form method="POST" class="d-flex gap-2 align-items-end flex-wrap" style="margin-top:10px">
            <input type="hidden" name="action"      value="add_subcourse">
            <input type="hidden" name="training_id" value="<?= $t['id'] ?>">
            <div>
                <label class="form-label">Sub-Course Name</label>
                <input type="text" name="sc_name" class="form-control" style="min-width:220px" required placeholder="e.g. Module 1 – Introduction">
            </div>
            <div>
                <label class="form-label">Pass Mark (%)</label>
                <input type="number" name="pass_mark" class="form-control" style="width:110px" value="50" min="0" max="100" required>
            </div>
            <button type="submit" class="btn-ghost"><i class="bi bi-plus"></i> Add Sub-Course</button>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>


<!-- ===== ADD TRAINING MODAL ===== -->
<div class="modal fade" id="addTrainingModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="add_training">
        <div class="modal-header">
            <h5 class="modal-title">Create New Training</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Training Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Customer Service Excellence 2024">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Brief overview of this training programme"></textarea>
                </div>
                <div class="col-6">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control">
                </div>
                <div class="col-6">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-accent"><i class="bi bi-check-lg"></i> Create Training</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- ===== EDIT TRAINING MODAL ===== -->
<div class="modal fade" id="editTrainingModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="edit_training">
        <input type="hidden" name="id"     id="et_id">
        <div class="modal-header">
            <h5 class="modal-title">Edit Training</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Training Name *</label>
                    <input type="text" name="name" id="et_name" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="et_description" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-6">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" id="et_start_date" class="form-control">
                </div>
                <div class="col-6">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" id="et_end_date" class="form-control">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-accent"><i class="bi bi-check-lg"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
function openEditTraining(t) {
    document.getElementById('et_id').value          = t.id;
    document.getElementById('et_name').value        = t.name;
    document.getElementById('et_description').value = t.description || '';
    document.getElementById('et_start_date').value  = t.start_date || '';
    document.getElementById('et_end_date').value    = t.end_date || '';
    new bootstrap.Modal(document.getElementById('editTrainingModal')).show();
}
</script>

<?php require 'footer.php'; ?>
