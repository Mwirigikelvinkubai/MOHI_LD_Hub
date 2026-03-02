<?php
/**
 * obj_index.php — Module 1: Objectives
 * CRUD for strategic L&D objectives.
 */

require_once 'config.php';

$pageTitle  = 'Objectives';
$activePage = 'objectives';

$pdo = getDB();

// ==================== POST HANDLERS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add') {
        $pdo->prepare("INSERT INTO objectives (title,description,owner,target_date,priority,status) VALUES(?,?,?,?,?,?)")
            ->execute([clean($_POST['title']), clean($_POST['description']), clean($_POST['owner']),
                       clean($_POST['target_date']), clean($_POST['priority']), clean($_POST['status'])]);
        setFlash('success', 'Objective added successfully.');
        redirect('obj_index.php');
    }

    if ($_POST['action'] === 'edit') {
        $pdo->prepare("UPDATE objectives SET title=?,description=?,owner=?,target_date=?,priority=?,status=? WHERE id=?")
            ->execute([clean($_POST['title']), clean($_POST['description']), clean($_POST['owner']),
                       clean($_POST['target_date']), clean($_POST['priority']), clean($_POST['status']),
                       (int)$_POST['id']]);
        setFlash('success', 'Objective updated.');
        redirect('obj_index.php');
    }

    if ($_POST['action'] === 'delete') {
        $pdo->prepare("DELETE FROM objectives WHERE id=?")->execute([(int)$_POST['id']]);
        setFlash('success', 'Objective deleted.');
        redirect('obj_index.php');
    }
}

$objectives = $pdo->query("SELECT o.*, (SELECT COUNT(*) FROM kpis WHERE objective_id=o.id) as kpi_count FROM objectives o ORDER BY o.created_at DESC")->fetchAll();

require 'header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <div class="section-title">1. Objectives</div>
        <div class="section-sub"><?= count($objectives) ?> objective(s) defined &nbsp;·&nbsp; <a href="obj_kpi.php">View KPIs →</a></div>
    </div>
    <button class="btn-accent" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg"></i> Add Objective
    </button>
</div>

<?php if (empty($objectives)): ?>
<div class="card text-center" style="padding:48px">
    <div style="font-size:48px;margin-bottom:12px;">🎯</div>
    <h5 style="font-family:'Barlow',sans-serif;color:var(--text)">No objectives yet</h5>
    <p class="text-muted-c">Click "Add Objective" to define your first L&D strategic objective.</p>
</div>
<?php else: ?>

<div class="row g-3">
<?php foreach ($objectives as $obj): ?>
<div class="col-lg-6">
    <div class="card h-100">
        <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
            <div style="flex:1">
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <span class="badge-priority <?= strtolower($obj['priority']) ?>"><?= htmlspecialchars($obj['priority']) ?></span>
                    <span class="badge-status <?= strtolower(str_replace(' ','-',$obj['status'])) ?>"><?= htmlspecialchars($obj['status']) ?></span>
                </div>
                <h5 style="font-family:'Barlow',sans-serif;font-size:15px;font-weight:700;color:var(--text);margin:6px 0 4px">
                    <?= htmlspecialchars($obj['title']) ?>
                </h5>
                <?php if ($obj['description']): ?>
                <p style="font-size:12.5px;color:var(--muted);margin:0 0 8px"><?= htmlspecialchars($obj['description']) ?></p>
                <?php endif; ?>
                <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:11.5px;color:var(--muted)">
                    <?php if ($obj['owner']): ?>
                    <span><i class="bi bi-person text-accent"></i> <?= htmlspecialchars($obj['owner']) ?></span>
                    <?php endif; ?>
                    <?php if ($obj['target_date']): ?>
                    <span><i class="bi bi-calendar3 text-accent"></i> Target: <?= htmlspecialchars($obj['target_date']) ?></span>
                    <?php endif; ?>
                    <span><i class="bi bi-graph-up-arrow text-accent"></i>
                        <a href="obj_kpi.php?obj=<?= $obj['id'] ?>" style="color:var(--blue)">
                            <?= $obj['kpi_count'] ?> KPI<?= $obj['kpi_count'] != 1 ? 's' : '' ?>
                        </a>
                    </span>
                </div>
            </div>
            <div class="d-flex gap-1 flex-shrink-0">
                <button class="btn-edit-sm" onclick="openEdit(<?= htmlspecialchars(json_encode($obj)) ?>)">
                    <i class="bi bi-pencil"></i>
                </button>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this objective?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $obj['id'] ?>">
                    <button type="submit" class="btn-danger-sm"><i class="bi bi-trash"></i></button>
                </form>
            </div>
        </div>
        <div style="margin-top:auto;padding-top:12px;border-top:1px solid var(--border)">
            <a href="obj_kpi.php?obj=<?= $obj['id'] ?>" class="btn-ghost" style="font-size:12px;padding:5px 12px;">
                <i class="bi bi-graph-up-arrow"></i> Manage KPIs
            </a>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>


<!-- ADD MODAL -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-bullseye me-2"></i>Add New Objective</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Objective Title *</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Improve staff digital literacy by Q4 2025">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Brief description of this objective"></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Owner</label>
                    <input type="text" name="owner" class="form-control" placeholder="Name or role">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Target Date</label>
                    <input type="date" name="target_date" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <option value="High">High</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="Low">Low</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="Active" selected>Active</option>
                        <option value="On Hold">On Hold</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-accent"><i class="bi bi-check-lg"></i> Save Objective</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-header">
            <h5 class="modal-title">Edit Objective</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Objective Title *</label>
                    <input type="text" name="title" id="edit_title" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Owner</label>
                    <input type="text" name="owner" id="edit_owner" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Target Date</label>
                    <input type="date" name="target_date" id="edit_target_date" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Priority</label>
                    <select name="priority" id="edit_priority" class="form-select">
                        <option value="High">High</option>
                        <option value="Medium">Medium</option>
                        <option value="Low">Low</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" id="edit_status" class="form-select">
                        <option value="Active">Active</option>
                        <option value="On Hold">On Hold</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-accent"><i class="bi bi-check-lg"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEdit(obj) {
    document.getElementById('edit_id').value          = obj.id;
    document.getElementById('edit_title').value       = obj.title;
    document.getElementById('edit_description').value = obj.description || '';
    document.getElementById('edit_owner').value       = obj.owner || '';
    document.getElementById('edit_target_date').value = obj.target_date || '';
    document.getElementById('edit_priority').value    = obj.priority;
    document.getElementById('edit_status').value      = obj.status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require 'footer.php'; ?>
