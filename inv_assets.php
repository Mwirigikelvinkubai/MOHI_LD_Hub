<?php
/**
 * inv_assets.php — Module 2.1: Assets (Inventory)
 * Track L&D department physical and digital assets.
 */

require_once 'config.php';

$pageTitle  = 'Assets — Inventory';
$activePage = 'assets';

$pdo = getDB();

// ==================== POST HANDLERS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add_category') {
        try {
            $pdo->prepare("INSERT INTO asset_categories (name, description) VALUES(?,?)")
                ->execute([clean($_POST['cat_name']), clean($_POST['cat_desc'])]);
            setFlash('success', 'Category added.');
        } catch (PDOException $e) {
            setFlash('danger', 'Category name already exists.');
        }
        redirect('inv_assets.php');
    }

    if ($_POST['action'] === 'delete_category') {
        $pdo->prepare("DELETE FROM asset_categories WHERE id=?")->execute([(int)$_POST['id']]);
        setFlash('success', 'Category deleted.');
        redirect('inv_assets.php');
    }

    if ($_POST['action'] === 'add') {
        $pdo->prepare("INSERT INTO assets (category_id,name,description,serial_number,quantity,unit,location,condition_status,assigned_to,purchase_date,warranty_expiry,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([(int)$_POST['category_id'] ?: null, clean($_POST['name']),
                       clean($_POST['description']), clean($_POST['serial_number']),
                       (int)$_POST['quantity'] ?: 1, clean($_POST['unit']),
                       clean($_POST['location']), clean($_POST['condition_status']),
                       clean($_POST['assigned_to']), clean($_POST['purchase_date']),
                       clean($_POST['warranty_expiry']), clean($_POST['notes'])]);
        setFlash('success', 'Asset added.');
        redirect('inv_assets.php');
    }

    if ($_POST['action'] === 'edit') {
        $pdo->prepare("UPDATE assets SET category_id=?,name=?,description=?,serial_number=?,quantity=?,unit=?,location=?,condition_status=?,assigned_to=?,purchase_date=?,warranty_expiry=?,notes=? WHERE id=?")
            ->execute([(int)$_POST['category_id'] ?: null, clean($_POST['name']),
                       clean($_POST['description']), clean($_POST['serial_number']),
                       (int)$_POST['quantity'], clean($_POST['unit']),
                       clean($_POST['location']), clean($_POST['condition_status']),
                       clean($_POST['assigned_to']), clean($_POST['purchase_date']),
                       clean($_POST['warranty_expiry']), clean($_POST['notes']),
                       (int)$_POST['id']]);
        setFlash('success', 'Asset updated.');
        redirect('inv_assets.php');
    }

    if ($_POST['action'] === 'delete') {
        $pdo->prepare("DELETE FROM assets WHERE id=?")->execute([(int)$_POST['id']]);
        setFlash('success', 'Asset deleted.');
        redirect('inv_assets.php');
    }
}

// Fetch categories
$categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM assets WHERE category_id=c.id) as asset_count FROM asset_categories c ORDER BY c.name")->fetchAll();

// Fetch assets with category name
$assets = $pdo->query("
    SELECT a.*, c.name as category_name
    FROM assets a
    LEFT JOIN asset_categories c ON c.id = a.category_id
    ORDER BY a.created_at DESC
")->fetchAll();

// Stats
$totalAssets = count($assets);
$totalQty    = array_sum(array_column($assets, 'quantity'));
$goodCount   = count(array_filter($assets, fn($a) => $a['condition_status'] === 'Good'));

require 'header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <div class="section-title">2.1 Assets — Inventory</div>
        <div class="section-sub"><?= $totalAssets ?> asset record<?= $totalAssets != 1 ? 's' : '' ?> · <?= $totalQty ?> total items</div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn-ghost" data-bs-toggle="modal" data-bs-target="#catModal">
            <i class="bi bi-tags"></i> Categories
        </button>
        <button class="btn-accent" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg"></i> Add Asset
        </button>
    </div>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-box-seam-fill"></i></div>
            <div><div class="stat-value"><?= $totalAssets ?></div><div class="stat-label">Asset Records</div></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-stack"></i></div>
            <div><div class="stat-value"><?= $totalQty ?></div><div class="stat-label">Total Items</div></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-tags-fill"></i></div>
            <div><div class="stat-value"><?= count($categories) ?></div><div class="stat-label">Categories</div></div>
        </div>
    </div>
</div>

<!-- Assets Table -->
<div class="card">
    <div class="card-header-custom">
        <h5><i class="bi bi-box-seam-fill"></i> Asset Register (2.1)</h5>
    </div>
    <?php if (empty($assets)): ?>
    <div class="text-center" style="padding:32px">
        <div style="font-size:40px;margin-bottom:10px;">📦</div>
        <p class="text-muted-c">No assets yet. Click "Add Asset" to start your inventory.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table datatable">
            <thead>
                <tr>
                    <th>Asset Name</th>
                    <th>Category</th>
                    <th>Qty</th>
                    <th>Location</th>
                    <th>Condition</th>
                    <th>Assigned To</th>
                    <th>Serial No.</th>
                    <th>Purchase Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($assets as $a): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($a['name']) ?></strong>
                    <?php if ($a['description']): ?>
                    <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars(substr($a['description'],0,50)) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($a['category_name']): ?>
                    <span class="dept-pill"><?= htmlspecialchars($a['category_name']) ?></span>
                    <?php else: ?><span class="text-muted-c">—</span><?php endif; ?>
                </td>
                <td><strong><?= $a['quantity'] ?></strong> <span class="text-muted-c"><?= htmlspecialchars($a['unit']) ?></span></td>
                <td class="text-muted-c"><?= htmlspecialchars($a['location'] ?: '—') ?></td>
                <td>
                    <span class="badge-status <?= strtolower($a['condition_status']) ?>"><?= htmlspecialchars($a['condition_status']) ?></span>
                </td>
                <td class="text-muted-c"><?= htmlspecialchars($a['assigned_to'] ?: '—') ?></td>
                <td class="text-muted-c" style="font-size:12px"><?= htmlspecialchars($a['serial_number'] ?: '—') ?></td>
                <td class="text-muted-c" style="font-size:12px"><?= htmlspecialchars($a['purchase_date'] ?: '—') ?></td>
                <td style="white-space:nowrap">
                    <button class="btn-edit-sm" onclick="openEdit(<?= htmlspecialchars(json_encode($a)) ?>)">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete asset?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
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


<!-- ADD ASSET MODAL -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>Add New Asset</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Asset Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Projector Sony VPL-EW578">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Serial Number</label>
                    <input type="text" name="serial_number" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="quantity" class="form-control" value="1" min="1">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Unit</label>
                    <input type="text" name="unit" class="form-control" value="pcs" placeholder="pcs">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" placeholder="e.g. Training Room A">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Condition</label>
                    <select name="condition_status" class="form-select">
                        <option value="Good" selected>Good</option>
                        <option value="Fair">Fair</option>
                        <option value="Poor">Poor</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Assigned To</label>
                    <input type="text" name="assigned_to" class="form-control" placeholder="Name or team">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Purchase Date</label>
                    <input type="date" name="purchase_date" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Warranty Expiry</label>
                    <input type="date" name="warranty_expiry" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-accent"><i class="bi bi-check-lg"></i> Add Asset</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT ASSET MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="ea_id">
        <div class="modal-header">
            <h5 class="modal-title">Edit Asset</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Asset Name *</label>
                    <input type="text" name="name" id="ea_name" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select name="category_id" id="ea_cat" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="ea_desc" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Serial Number</label>
                    <input type="text" name="serial_number" id="ea_serial" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="quantity" id="ea_qty" class="form-control" min="1">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Unit</label>
                    <input type="text" name="unit" id="ea_unit" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" id="ea_loc" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Condition</label>
                    <select name="condition_status" id="ea_cond" class="form-select">
                        <option>Good</option><option>Fair</option><option>Poor</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Assigned To</label>
                    <input type="text" name="assigned_to" id="ea_assign" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Purchase Date</label>
                    <input type="date" name="purchase_date" id="ea_pdate" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Warranty Expiry</label>
                    <input type="date" name="warranty_expiry" id="ea_wdate" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" id="ea_notes" class="form-control" rows="2"></textarea>
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

<!-- CATEGORIES MODAL -->
<div class="modal fade" id="catModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-tags me-2"></i>Asset Categories</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <!-- Add category form -->
            <form method="POST" class="d-flex gap-2 mb-3">
                <input type="hidden" name="action" value="add_category">
                <input type="text" name="cat_name" class="form-control" placeholder="New category name" required>
                <input type="text" name="cat_desc" class="form-control" placeholder="Description (optional)">
                <button type="submit" class="btn-accent" style="white-space:nowrap"><i class="bi bi-plus"></i> Add</button>
            </form>
            <!-- Existing categories -->
            <?php if (empty($categories)): ?>
            <p class="text-muted-c" style="font-size:13px">No categories yet.</p>
            <?php else: ?>
            <table class="table" style="font-size:13px">
                <thead><tr><th>Category</th><th>Assets</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($categories as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['name']) ?>
                        <?php if ($c['description']): ?>
                        <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($c['description']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= $c['asset_count'] ?></td>
                    <td>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete category?')">
                            <input type="hidden" name="action" value="delete_category">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn-danger-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
  </div>
</div>

<script>
function openEdit(a) {
    document.getElementById('ea_id').value     = a.id;
    document.getElementById('ea_name').value   = a.name;
    document.getElementById('ea_cat').value    = a.category_id || '';
    document.getElementById('ea_desc').value   = a.description || '';
    document.getElementById('ea_serial').value = a.serial_number || '';
    document.getElementById('ea_qty').value    = a.quantity;
    document.getElementById('ea_unit').value   = a.unit;
    document.getElementById('ea_loc').value    = a.location || '';
    document.getElementById('ea_cond').value   = a.condition_status;
    document.getElementById('ea_assign').value = a.assigned_to || '';
    document.getElementById('ea_pdate').value  = a.purchase_date || '';
    document.getElementById('ea_wdate').value  = a.warranty_expiry || '';
    document.getElementById('ea_notes').value  = a.notes || '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require 'footer.php'; ?>
