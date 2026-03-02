<?php
/**
 * modules.php — Custom Modules Manager
 * Allows adding new tiles/modules to the MOHI LD HUB portal.
 * This is the scalability feature — extend the hub with anything.
 */

require_once 'config.php';

$pageTitle  = 'Manage Modules';
$activePage = 'modules';

$pdo = getDB();

// ==================== POST HANDLERS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add') {
        $pdo->prepare("INSERT INTO hub_modules (title,description,icon,color,link_url,sort_order) VALUES(?,?,?,?,?,?)")
            ->execute([clean($_POST['title']), clean($_POST['description']),
                       clean($_POST['icon']), clean($_POST['color']),
                       clean($_POST['link_url']), (int)$_POST['sort_order']]);
        setFlash('success', '"' . clean($_POST['title']) . '" module added to hub.');
        redirect('modules.php');
    }

    if ($_POST['action'] === 'edit') {
        $pdo->prepare("UPDATE hub_modules SET title=?,description=?,icon=?,color=?,link_url=?,sort_order=?,is_active=? WHERE id=?")
            ->execute([clean($_POST['title']), clean($_POST['description']),
                       clean($_POST['icon']), clean($_POST['color']),
                       clean($_POST['link_url']), (int)$_POST['sort_order'],
                       isset($_POST['is_active']) ? 1 : 0,
                       (int)$_POST['id']]);
        setFlash('success', 'Module updated.');
        redirect('modules.php');
    }

    if ($_POST['action'] === 'delete') {
        $pdo->prepare("DELETE FROM hub_modules WHERE id=?")->execute([(int)$_POST['id']]);
        setFlash('success', 'Module removed from hub.');
        redirect('modules.php');
    }

    if ($_POST['action'] === 'toggle') {
        $pdo->prepare("UPDATE hub_modules SET is_active = 1 - is_active WHERE id=?")->execute([(int)$_POST['id']]);
        redirect('modules.php');
    }
}

$modules = $pdo->query("SELECT * FROM hub_modules ORDER BY sort_order ASC, created_at ASC")->fetchAll();

require 'header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <div class="section-title">Manage Hub Modules</div>
        <div class="section-sub">Add, edit, or hide custom modules on the MOHI LD HUB portal</div>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn-ghost"><i class="bi bi-house"></i> Back to Hub</a>
        <button class="btn-accent" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg"></i> New Module
        </button>
    </div>
</div>

<!-- Info banner -->
<div class="alert alert-info mb-4">
    <i class="bi bi-info-circle"></i>
    <span>Custom modules appear as cards on the <a href="index.php" style="color:var(--blue);font-weight:600">Hub Home</a> and in the sidebar.
    Use them to link to external tools, future internal pages, or just bookmark important resources.</span>
</div>

<!-- Core modules (non-editable reference) -->
<div class="card mb-4">
    <div class="card-header-custom">
        <h5><i class="bi bi-lock-fill"></i> Built-in Modules</h5>
        <span style="font-size:12px;color:var(--muted)">These are permanent and cannot be removed</span>
    </div>
    <div class="row g-3">
        <?php
        $builtins = [
            ['1', 'bi-bullseye',         'navy',  'Objectives',         'obj_index.php'],
            ['2', 'bi-box-seam-fill',    'green', 'Inventory',          'inv_assets.php'],
            ['3', 'bi-mortarboard-fill', 'blue',  'Staff Training Data','train_dashboard.php'],
        ];
        foreach ($builtins as $b): ?>
        <div class="col-md-4">
            <div style="display:flex;align-items:center;gap:12px;padding:14px;border:1px solid var(--border);border-radius:var(--radius);background:rgba(0,0,0,0.15)">
                <div class="hub-card-icon <?= $b[2] ?>" style="width:38px;height:38px;font-size:18px;flex-shrink:0">
                    <i class="bi <?= $b[0] ?>"></i>
                </div>
                <div>
                    <div style="font-size:10px;color:var(--blue);font-family:'Barlow',sans-serif;font-weight:700;text-transform:uppercase;letter-spacing:0.08em">Module <?= $b[0] ?></div>
                    <div style="font-family:'Barlow',sans-serif;font-size:13px;font-weight:700;color:var(--text)"><?= $b[3] ?></div>
                    <a href="<?= $b[4] ?>" style="font-size:11px;color:var(--muted)">→ <?= $b[4] ?></a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Custom modules list -->
<div class="card">
    <div class="card-header-custom">
        <h5><i class="bi bi-grid-1x2"></i> Custom Modules <?= count($modules) > 0 ? '(' . count($modules) . ')' : '' ?></h5>
    </div>

    <?php if (empty($modules)): ?>
    <div class="text-center" style="padding:40px">
        <div style="font-size:44px;margin-bottom:12px;">🔌</div>
        <h5 style="font-family:'Barlow',sans-serif;color:var(--text)">No custom modules yet</h5>
        <p class="text-muted-c" style="margin-bottom:16px">Use "New Module" to add your own tiles to the hub. You can link to external tools, new features, or any URL.</p>
        <p class="text-muted-c" style="font-size:12px">Examples: Policy Library, Comms Calendar, Budget Tracker, Reporting Portal…</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table" style="font-size:13.5px">
            <thead>
                <tr>
                    <th>Icon</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Link</th>
                    <th>Order</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($modules as $m): ?>
            <tr>
                <td>
                    <div class="hub-card-icon <?= htmlspecialchars($m['color']) ?>" style="width:36px;height:36px;font-size:16px">
                        <i class="bi <?= htmlspecialchars($m['icon']) ?>"></i>
                    </div>
                </td>
                <td><strong><?= htmlspecialchars($m['title']) ?></strong></td>
                <td class="text-muted-c"><?= htmlspecialchars(substr($m['description'] ?? '',0,60)) ?></td>
                <td>
                    <?php if ($m['link_url']): ?>
                    <a href="<?= htmlspecialchars($m['link_url']) ?>" target="_blank" style="font-size:12px;color:var(--blue)">
                        <?= htmlspecialchars(substr($m['link_url'],0,40)) ?>
                    </a>
                    <?php else: ?><span class="text-muted-c">—</span><?php endif; ?>
                </td>
                <td class="text-muted-c"><?= $m['sort_order'] ?></td>
                <td>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                        <button type="submit" class="badge-status <?= $m['is_active'] ? 'active' : 'on-hold' ?>" style="cursor:pointer;border:none;font-size:11px;padding:3px 10px;border-radius:20px;font-family:'Barlow',sans-serif;font-weight:700">
                            <?= $m['is_active'] ? 'Visible' : 'Hidden' ?>
                        </button>
                    </form>
                </td>
                <td style="white-space:nowrap">
                    <button class="btn-edit-sm" onclick="openEdit(<?= htmlspecialchars(json_encode($m)) ?>)">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Remove this module?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                        <button type="submit" class="btn-danger-sm"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Icon suggestions -->
<div class="card mt-4" style="font-size:12px">
    <div class="card-header-custom">
        <h5><i class="bi bi-palette"></i> Icon &amp; Color Reference</h5>
    </div>
    <div style="color:var(--muted);line-height:1.9">
        <strong style="color:var(--text)">Colors:</strong>
        <code>blue</code> <code>green</code> <code>gold</code> <code>orange</code> <code>navy</code> <code>teal</code>
        &nbsp;&nbsp;
        <strong style="color:var(--text)">Example icons:</strong>
        <code>bi-calendar3</code> <code>bi-file-earmark-text</code> <code>bi-people</code>
        <code>bi-chat-dots</code> <code>bi-gear</code> <code>bi-globe</code>
        <code>bi-megaphone</code> <code>bi-clipboard-data</code> <code>bi-briefcase</code>
        <code>bi-book</code> <code>bi-award</code> <code>bi-shield-check</code>
        <br>
        <a href="https://icons.getbootstrap.com" target="_blank" style="color:var(--blue)">→ Full Bootstrap Icons list</a>
    </div>
</div>


<!-- ADD MODAL -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-grid-1x2 me-2"></i>Add New Hub Module</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Module Title *</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Policy Library">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Icon (Bootstrap icon name)</label>
                    <input type="text" name="icon" class="form-control" value="bi-grid" placeholder="bi-grid">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Card Color</label>
                    <select name="color" class="form-select">
                        <option value="blue">Blue</option>
                        <option value="green">Green</option>
                        <option value="gold">Gold</option>
                        <option value="orange">Orange</option>
                        <option value="navy">Navy</option>
                        <option value="teal">Teal</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="What does this module contain or link to?"></textarea>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Link URL (optional)</label>
                    <input type="text" name="link_url" class="form-control" placeholder="https://... or leave blank for placeholder">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="10" min="1">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-accent"><i class="bi bi-check-lg"></i> Add to Hub</button>
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
        <input type="hidden" name="id" id="em_id">
        <div class="modal-header">
            <h5 class="modal-title">Edit Module</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" id="em_title" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Icon</label>
                    <input type="text" name="icon" id="em_icon" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Color</label>
                    <select name="color" id="em_color" class="form-select">
                        <option value="blue">Blue</option>
                        <option value="green">Green</option>
                        <option value="gold">Gold</option>
                        <option value="orange">Orange</option>
                        <option value="navy">Navy</option>
                        <option value="teal">Teal</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="em_desc" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Link URL</label>
                    <input type="text" name="link_url" id="em_url" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" id="em_order" class="form-control" min="1">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="em_active" value="1">
                        <label class="form-check-label" for="em_active" style="color:var(--muted);font-size:12px">Visible</label>
                    </div>
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
function openEdit(m) {
    document.getElementById('em_id').value    = m.id;
    document.getElementById('em_title').value = m.title;
    document.getElementById('em_icon').value  = m.icon;
    document.getElementById('em_color').value = m.color;
    document.getElementById('em_desc').value  = m.description || '';
    document.getElementById('em_url').value   = m.link_url || '';
    document.getElementById('em_order').value = m.sort_order;
    document.getElementById('em_active').checked = m.is_active == 1;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require 'footer.php'; ?>
