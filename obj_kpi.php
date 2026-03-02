<?php
/**
 * obj_kpi.php — Module 1.1: KPIs
 * Manage Key Performance Indicators, optionally filtered by Objective.
 */

require_once 'config.php';

$pageTitle  = 'KPIs';
$activePage = 'kpi';

$pdo = getDB();

$filterObjId = (int)($_GET['obj'] ?? 0);

// ==================== POST HANDLERS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add') {
        $pdo->prepare("INSERT INTO kpis (objective_id,title,description,target_value,current_value,unit,frequency,owner,status,baseline,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([(int)$_POST['objective_id'] ?: null, clean($_POST['title']),
                       clean($_POST['description']), clean($_POST['target_value']),
                       clean($_POST['current_value']), clean($_POST['unit']),
                       clean($_POST['frequency']), clean($_POST['owner']),
                       clean($_POST['status']), clean($_POST['baseline']),
                       clean($_POST['notes'])]);
        setFlash('success', 'KPI added.');
        redirect('obj_kpi.php' . ($filterObjId ? "?obj=$filterObjId" : ''));
    }

    if ($_POST['action'] === 'edit') {
        $pdo->prepare("UPDATE kpis SET objective_id=?,title=?,description=?,target_value=?,current_value=?,unit=?,frequency=?,owner=?,status=?,baseline=?,notes=?,updated_at=datetime('now') WHERE id=?")
            ->execute([(int)$_POST['objective_id'] ?: null, clean($_POST['title']),
                       clean($_POST['description']), clean($_POST['target_value']),
                       clean($_POST['current_value']), clean($_POST['unit']),
                       clean($_POST['frequency']), clean($_POST['owner']),
                       clean($_POST['status']), clean($_POST['baseline']),
                       clean($_POST['notes']), (int)$_POST['id']]);
        setFlash('success', 'KPI updated.');
        redirect('obj_kpi.php' . ($filterObjId ? "?obj=$filterObjId" : ''));
    }

    if ($_POST['action'] === 'delete') {
        $pdo->prepare("DELETE FROM kpis WHERE id=?")->execute([(int)$_POST['id']]);
        setFlash('success', 'KPI deleted.');
        redirect('obj_kpi.php' . ($filterObjId ? "?obj=$filterObjId" : ''));
    }
}

// Fetch objectives for dropdowns
$objectives = $pdo->query("SELECT id, title FROM objectives ORDER BY title")->fetchAll();

// Fetch KPIs
$where = $filterObjId ? "WHERE k.objective_id = $filterObjId" : '';
$kpis = $pdo->query("
    SELECT k.*, o.title as obj_title,
           (SELECT COUNT(*) FROM raci_items WHERE kpi_id=k.id) as raci_count
    FROM kpis k
    LEFT JOIN objectives o ON o.id = k.objective_id
    $where
    ORDER BY k.created_at DESC
")->fetchAll();

// If filtering, get obj name
$filterObj = null;
if ($filterObjId) {
    $filterObj = $pdo->prepare("SELECT * FROM objectives WHERE id=?");
    $filterObj->execute([$filterObjId]);
    $filterObj = $filterObj->fetch();
}

require 'header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <div class="section-title">1.1 KPIs</div>
        <div class="section-sub">
            <?php if ($filterObj): ?>
                Showing KPIs for: <strong><?= htmlspecialchars($filterObj['title']) ?></strong>
                &nbsp;·&nbsp; <a href="obj_kpi.php">Show all</a>
            <?php else: ?>
                <?= count($kpis) ?> KPI<?= count($kpis) != 1 ? 's' : '' ?> defined
            <?php endif; ?>
            &nbsp;·&nbsp; <a href="obj_raci.php">View RACI →</a>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="obj_index.php" class="btn-ghost"><i class="bi bi-arrow-left"></i> Objectives</a>
        <button class="btn-accent" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg"></i> Add KPI
        </button>
    </div>
</div>

<?php if (empty($kpis)): ?>
<div class="card text-center" style="padding:48px">
    <div style="font-size:48px;margin-bottom:12px;">📊</div>
    <h5 style="font-family:'Barlow',sans-serif;color:var(--text)">No KPIs yet</h5>
    <p class="text-muted-c">Click "Add KPI" to define your first Key Performance Indicator.</p>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table datatable">
            <thead>
                <tr>
                    <th>KPI Title</th>
                    <th>Objective</th>
                    <th>Target</th>
                    <th>Current</th>
                    <th>Frequency</th>
                    <th>Owner</th>
                    <th>Status</th>
                    <th>RACI</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($kpis as $k): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($k['title']) ?></strong>
                    <?php if ($k['description']): ?>
                    <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars(substr($k['description'],0,60)) . (strlen($k['description'])>60?'…':'') ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($k['obj_title']): ?>
                    <a href="obj_kpi.php?obj=<?= $k['objective_id'] ?>" style="font-size:12px;color:var(--blue)">
                        <?= htmlspecialchars(substr($k['obj_title'],0,40)) ?>
                    </a>
                    <?php else: ?>
                    <span class="text-muted-c">—</span>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap">
                    <strong><?= htmlspecialchars($k['target_value'] ?: '—') ?></strong>
                    <?php if ($k['unit']): ?><span class="text-muted-c" style="font-size:11px"> <?= htmlspecialchars($k['unit']) ?></span><?php endif; ?>
                </td>
                <td style="white-space:nowrap">
                    <?= htmlspecialchars($k['current_value'] ?: '—') ?>
                    <?php if ($k['unit'] && $k['current_value']): ?><span class="text-muted-c" style="font-size:11px"> <?= htmlspecialchars($k['unit']) ?></span><?php endif; ?>
                </td>
                <td class="text-muted-c"><?= htmlspecialchars($k['frequency']) ?></td>
                <td class="text-muted-c"><?= htmlspecialchars($k['owner'] ?: '—') ?></td>
                <td>
                    <span class="badge-status <?= strtolower(str_replace([' ','_'],'-',$k['status'])) ?>">
                        <?= htmlspecialchars($k['status']) ?>
                    </span>
                </td>
                <td>
                    <a href="obj_raci.php?kpi=<?= $k['id'] ?>" style="font-size:12px;color:var(--blue)">
                        <?= $k['raci_count'] ?> item<?= $k['raci_count'] != 1 ? 's' : '' ?>
                    </a>
                </td>
                <td style="white-space:nowrap">
                    <button class="btn-edit-sm" onclick="openEdit(<?= htmlspecialchars(json_encode($k)) ?>)">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this KPI?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $k['id'] ?>">
                        <button type="submit" class="btn-danger-sm"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>


<!-- ADD MODAL -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-graph-up-arrow me-2"></i>Add New KPI</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">KPI Title *</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. % staff completing digital skills training">
                </div>
                <div class="col-12">
                    <label class="form-label">Linked Objective</label>
                    <select name="objective_id" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($objectives as $o): ?>
                        <option value="<?= $o['id'] ?>" <?= $filterObjId == $o['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($o['title']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="What this KPI measures"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Data source, methodology, etc."></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Target Value</label>
                    <input type="text" name="target_value" class="form-control" placeholder="e.g. 80">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Current Value</label>
                    <input type="text" name="current_value" class="form-control" placeholder="e.g. 45">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unit</label>
                    <input type="text" name="unit" class="form-control" placeholder="e.g. %, count, score">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Baseline</label>
                    <input type="text" name="baseline" class="form-control" placeholder="Starting value">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Frequency</label>
                    <select name="frequency" class="form-select">
                        <option value="Weekly">Weekly</option>
                        <option value="Monthly" selected>Monthly</option>
                        <option value="Quarterly">Quarterly</option>
                        <option value="Annually">Annually</option>
                        <option value="Ad-hoc">Ad-hoc</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Owner</label>
                    <input type="text" name="owner" class="form-control" placeholder="Name or role">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="On Track">On Track</option>
                        <option value="At Risk">At Risk</option>
                        <option value="Behind">Behind</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-accent"><i class="bi bi-check-lg"></i> Save KPI</button>
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
        <input type="hidden" name="id" id="ek_id">
        <div class="modal-header">
            <h5 class="modal-title">Edit KPI</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">KPI Title *</label>
                    <input type="text" name="title" id="ek_title" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Linked Objective</label>
                    <select name="objective_id" id="ek_obj" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($objectives as $o): ?>
                        <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="ek_desc" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" id="ek_notes" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Target Value</label>
                    <input type="text" name="target_value" id="ek_target" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Current Value</label>
                    <input type="text" name="current_value" id="ek_current" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unit</label>
                    <input type="text" name="unit" id="ek_unit" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Baseline</label>
                    <input type="text" name="baseline" id="ek_baseline" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Frequency</label>
                    <select name="frequency" id="ek_freq" class="form-select">
                        <option>Weekly</option><option>Monthly</option><option>Quarterly</option>
                        <option>Annually</option><option>Ad-hoc</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Owner</label>
                    <input type="text" name="owner" id="ek_owner" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" id="ek_status" class="form-select">
                        <option>On Track</option><option>At Risk</option>
                        <option>Behind</option><option>Completed</option>
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
function openEdit(k) {
    document.getElementById('ek_id').value       = k.id;
    document.getElementById('ek_title').value    = k.title;
    document.getElementById('ek_obj').value      = k.objective_id || '';
    document.getElementById('ek_desc').value     = k.description || '';
    document.getElementById('ek_notes').value    = k.notes || '';
    document.getElementById('ek_target').value   = k.target_value || '';
    document.getElementById('ek_current').value  = k.current_value || '';
    document.getElementById('ek_unit').value     = k.unit || '';
    document.getElementById('ek_baseline').value = k.baseline || '';
    document.getElementById('ek_freq').value     = k.frequency;
    document.getElementById('ek_owner').value    = k.owner || '';
    document.getElementById('ek_status').value   = k.status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require 'footer.php'; ?>
