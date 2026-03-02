<?php
/**
 * obj_raci.php — Module 1.1.1: RACI
 * Responsible / Accountable / Consulted / Informed matrix for KPIs.
 */

require_once 'config.php';

$pageTitle  = 'RACI Chart';
$activePage = 'raci';

$pdo = getDB();

$filterKpiId = (int)($_GET['kpi'] ?? 0);

// ==================== POST HANDLERS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add') {
        $pdo->prepare("INSERT INTO raci_items (kpi_id,activity,responsible,accountable,consulted,informed,notes) VALUES(?,?,?,?,?,?,?)")
            ->execute([(int)$_POST['kpi_id'] ?: null, clean($_POST['activity']),
                       clean($_POST['responsible']), clean($_POST['accountable']),
                       clean($_POST['consulted']), clean($_POST['informed']),
                       clean($_POST['notes'])]);
        setFlash('success', 'RACI item added.');
        redirect('obj_raci.php' . ($filterKpiId ? "?kpi=$filterKpiId" : ''));
    }

    if ($_POST['action'] === 'edit') {
        $pdo->prepare("UPDATE raci_items SET kpi_id=?,activity=?,responsible=?,accountable=?,consulted=?,informed=?,notes=? WHERE id=?")
            ->execute([(int)$_POST['kpi_id'] ?: null, clean($_POST['activity']),
                       clean($_POST['responsible']), clean($_POST['accountable']),
                       clean($_POST['consulted']), clean($_POST['informed']),
                       clean($_POST['notes']), (int)$_POST['id']]);
        setFlash('success', 'RACI item updated.');
        redirect('obj_raci.php' . ($filterKpiId ? "?kpi=$filterKpiId" : ''));
    }

    if ($_POST['action'] === 'delete') {
        $pdo->prepare("DELETE FROM raci_items WHERE id=?")->execute([(int)$_POST['id']]);
        setFlash('success', 'RACI item deleted.');
        redirect('obj_raci.php' . ($filterKpiId ? "?kpi=$filterKpiId" : ''));
    }
}

// Fetch KPIs for dropdown
$kpis = $pdo->query("SELECT k.id, k.title, o.title as obj_title FROM kpis k LEFT JOIN objectives o ON o.id=k.objective_id ORDER BY k.title")->fetchAll();

// Fetch RACI items
$where = $filterKpiId ? "WHERE r.kpi_id = $filterKpiId" : '';
$raciItems = $pdo->query("
    SELECT r.*, k.title as kpi_title
    FROM raci_items r
    LEFT JOIN kpis k ON k.id = r.kpi_id
    $where
    ORDER BY r.created_at ASC
")->fetchAll();

// Filter label
$filterKpi = null;
if ($filterKpiId) {
    $filterKpi = $pdo->prepare("SELECT * FROM kpis WHERE id=?");
    $filterKpi->execute([$filterKpiId]);
    $filterKpi = $filterKpi->fetch();
}

require 'header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <div class="section-title">1.1.1 RACI Chart</div>
        <div class="section-sub">
            <?php if ($filterKpi): ?>
                KPI: <strong><?= htmlspecialchars($filterKpi['title']) ?></strong>
                &nbsp;·&nbsp; <a href="obj_raci.php">Show all</a>
            <?php else: ?>
                <?= count($raciItems) ?> RACI item<?= count($raciItems) != 1 ? 's' : '' ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="obj_kpi.php" class="btn-ghost"><i class="bi bi-arrow-left"></i> KPIs</a>
        <button class="btn-accent" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg"></i> Add RACI Item
        </button>
    </div>
</div>

<!-- RACI legend -->
<div class="d-flex gap-3 mb-4 flex-wrap">
    <div class="d-flex align-items-center gap-2">
        <span class="raci-role R">R</span>
        <span style="font-size:12px;color:var(--muted)"><strong style="color:var(--text)">Responsible</strong> — Does the work</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="raci-role A">A</span>
        <span style="font-size:12px;color:var(--muted)"><strong style="color:var(--text)">Accountable</strong> — Final decision owner</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="raci-role C">C</span>
        <span style="font-size:12px;color:var(--muted)"><strong style="color:var(--text)">Consulted</strong> — Input requested</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="raci-role I">I</span>
        <span style="font-size:12px;color:var(--muted)"><strong style="color:var(--text)">Informed</strong> — Kept in the loop</span>
    </div>
</div>

<?php if (empty($raciItems)): ?>
<div class="card text-center" style="padding:48px">
    <div style="font-size:48px;margin-bottom:12px;">🗂️</div>
    <h5 style="font-family:'Barlow',sans-serif;color:var(--text)">No RACI items yet</h5>
    <p class="text-muted-c">Click "Add RACI Item" to map responsibilities for your KPIs.</p>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table datatable raci-table">
            <thead>
                <tr>
                    <th>Activity / Task</th>
                    <th>Linked KPI</th>
                    <th><span class="raci-role R">R</span> Responsible</th>
                    <th><span class="raci-role A">A</span> Accountable</th>
                    <th><span class="raci-role C">C</span> Consulted</th>
                    <th><span class="raci-role I">I</span> Informed</th>
                    <th>Notes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($raciItems as $r): ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['activity']) ?></strong></td>
                <td>
                    <?php if ($r['kpi_title']): ?>
                    <a href="obj_raci.php?kpi=<?= $r['kpi_id'] ?>" style="font-size:12px;color:var(--blue)">
                        <?= htmlspecialchars(substr($r['kpi_title'],0,40)) ?>
                    </a>
                    <?php else: ?><span class="text-muted-c">—</span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars($r['responsible'] ?: '—') ?></td>
                <td><?= htmlspecialchars($r['accountable'] ?: '—') ?></td>
                <td class="text-muted-c"><?= htmlspecialchars($r['consulted'] ?: '—') ?></td>
                <td class="text-muted-c"><?= htmlspecialchars($r['informed'] ?: '—') ?></td>
                <td class="text-muted-c" style="font-size:12px"><?= htmlspecialchars(substr($r['notes'] ?? '',0,50)) ?></td>
                <td style="white-space:nowrap">
                    <button class="btn-edit-sm" onclick="openEdit(<?= htmlspecialchars(json_encode($r)) ?>)">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this item?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
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
            <h5 class="modal-title"><i class="bi bi-grid-3x3 me-2"></i>Add RACI Item</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Activity / Task *</label>
                    <input type="text" name="activity" class="form-control" required placeholder="e.g. Compile monthly training report">
                </div>
                <div class="col-12">
                    <label class="form-label">Linked KPI</label>
                    <select name="kpi_id" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($kpis as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $filterKpiId == $k['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($k['title']) ?><?= $k['obj_title'] ? ' ['.htmlspecialchars($k['obj_title']).']' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><span class="raci-role R">R</span> Responsible</label>
                    <input type="text" name="responsible" class="form-control" placeholder="Who does the work?">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><span class="raci-role A">A</span> Accountable</label>
                    <input type="text" name="accountable" class="form-control" placeholder="Who is ultimately answerable?">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><span class="raci-role C">C</span> Consulted</label>
                    <input type="text" name="consulted" class="form-control" placeholder="Whose input is needed?">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><span class="raci-role I">I</span> Informed</label>
                    <input type="text" name="informed" class="form-control" placeholder="Who should be kept informed?">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Additional context"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-accent"><i class="bi bi-check-lg"></i> Save RACI Item</button>
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
        <input type="hidden" name="id" id="er_id">
        <div class="modal-header">
            <h5 class="modal-title">Edit RACI Item</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Activity / Task *</label>
                    <input type="text" name="activity" id="er_activity" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Linked KPI</label>
                    <select name="kpi_id" id="er_kpi" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($kpis as $k): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Responsible</label>
                    <input type="text" name="responsible" id="er_resp" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Accountable</label>
                    <input type="text" name="accountable" id="er_acct" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Consulted</label>
                    <input type="text" name="consulted" id="er_cons" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Informed</label>
                    <input type="text" name="informed" id="er_info" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" id="er_notes" class="form-control" rows="2"></textarea>
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
function openEdit(r) {
    document.getElementById('er_id').value       = r.id;
    document.getElementById('er_activity').value = r.activity;
    document.getElementById('er_kpi').value      = r.kpi_id || '';
    document.getElementById('er_resp').value     = r.responsible || '';
    document.getElementById('er_acct').value     = r.accountable || '';
    document.getElementById('er_cons').value     = r.consulted || '';
    document.getElementById('er_info').value     = r.informed || '';
    document.getElementById('er_notes').value    = r.notes || '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require 'footer.php'; ?>
