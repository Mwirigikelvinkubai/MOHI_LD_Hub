<?php
/**
 * staff.php — Staff Management
 * Features:
 *   - View all staff in a searchable/sortable DataTable
 *   - Add new staff (modal form)
 *   - Edit existing staff (modal form)
 *   - Delete staff
 *   - Batch import via CSV
 */

require_once 'config.php';

$pageTitle  = 'Staff Management';
$activePage = 'train_staff';

$pdo = getDB();

// ---- Handle DELETE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();

    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("DELETE FROM staff WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        setFlash('success', 'Staff member deleted.');
        redirect('train_staff.php');
    }

    // ---- Handle ADD ----
    if ($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("
            INSERT INTO staff (staff_no, full_name, job_title, workstation, department, email)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        try {
            $stmt->execute([
                clean($_POST['staff_no']),
                clean($_POST['full_name']),
                clean($_POST['job_title']),
                clean($_POST['workstation']),
                clean($_POST['department']),
                clean($_POST['email']),
            ]);
            setFlash('success', 'Staff member added successfully.');
        } catch (PDOException $e) {
            // UNIQUE constraint on staff_no
            setFlash('danger', 'That email address already exists. Each staff member must have a unique email.');
        }
        redirect('train_staff.php');
    }

    // ---- Handle EDIT ----
    if ($_POST['action'] === 'edit') {
        $stmt = $pdo->prepare("
            UPDATE staff SET staff_no=?, full_name=?, job_title=?, workstation=?, department=?, email=?
            WHERE id=?
        ");
        try {
            $stmt->execute([
                clean($_POST['staff_no']),
                clean($_POST['full_name']),
                clean($_POST['job_title']),
                clean($_POST['workstation']),
                clean($_POST['department']),
                clean($_POST['email']),
                (int)$_POST['id'],
            ]);
            setFlash('success', 'Staff record updated.');
        } catch (PDOException $e) {
            setFlash('danger', 'Update failed: that email address is already used by another staff member.');
        }
        redirect('train_staff.php');
    }

    // ---- Handle CSV IMPORT ----
    if ($_POST['action'] === 'import_csv' && isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            setFlash('danger', 'File upload error.');
            redirect('train_staff.php');
        }

        // Server-side file type validation (client accept=".csv" is easily bypassed)
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mimeType = mime_content_type($file['tmp_name']);
        $allowedExts   = ['csv'];
        $allowedMimes  = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];

        if (!in_array($ext, $allowedExts) || !in_array($mimeType, $allowedMimes)) {
            setFlash('danger', 'Invalid file type. Please upload a .csv file only.');
            redirect('train_staff.php');
        }

        // Enforce a reasonable file size limit (5 MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            setFlash('danger', 'File too large. Maximum size is 5 MB.');
            redirect('train_staff.php');
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            setFlash('danger', 'Cannot open uploaded file.');
            redirect('train_staff.php');
        }

        $inserted = 0;
        $skipped  = 0;
        $rowNum   = 0;

        // Prepare insert statement once (reuse in loop — efficient!)
        $stmt = $pdo->prepare("
            INSERT OR IGNORE INTO staff (staff_no, full_name, job_title, workstation, department, email)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            // Skip header row
            if ($rowNum === 1) continue;

            // Skip blank rows
            if (empty(array_filter($row))) continue;

            // Pad row to 6 columns (in case some are missing)
            $row = array_pad($row, 6, '');

            [$staff_no, $full_name, $job_title, $workstation, $department, $email] = $row;

            // Basic validation — require both staff_no and email
            if (empty(trim($staff_no)) || empty(trim($full_name)) || empty(trim($email))) {
                $skipped++;
                continue;
            }

            $stmt->execute([
                clean($staff_no), clean($full_name), clean($job_title),
                clean($workstation), clean($department), clean($email)
            ]);

            // rowCount() = 1 if inserted, 0 if duplicate (INSERT OR IGNORE)
            $stmt->rowCount() > 0 ? $inserted++ : $skipped++;
        }

        fclose($handle);
        setFlash('success', "CSV import complete: {$inserted} inserted, {$skipped} skipped (duplicates/invalid).");
        redirect('train_staff.php');
    }
}

// ---- Fetch all staff ----
$staff = $pdo->query("SELECT * FROM staff ORDER BY full_name ASC")->fetchAll();

// Unique departments for filter dropdown
$departments = $pdo->query("SELECT DISTINCT department FROM staff WHERE department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

require 'header.php';
?>

<!-- Page header -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <div class="section-title">Staff Directory</div>
        <div class="section-sub"><?= number_format(count($staff)) ?> staff members registered</div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn-ghost" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="bi bi-upload"></i> Import CSV
        </button>
        <button class="btn-accent" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg"></i> Add Staff
        </button>
    </div>
</div>

<!-- CSV format hint -->
<div class="alert alert-success" style="font-size:12px;">
    <i class="bi bi-info-circle"></i>
    <strong>CSV Format:</strong> Staff No | Full Name | Job Title | Work Station | Department | Email (required — must be unique)
    (Row 1 = header, ignored automatically)
</div>

<!-- Staff Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table datatable" id="staffTable">
            <thead>
                <tr>
                    <th>Staff No</th>
                    <th>Full Name</th>
                    <th>Job Title</th>
                    <th>Work Station</th>
                    <th>Department</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staff as $s): ?>
                <tr>
                    <td><span class="text-accent" style="font-weight:600"><?= htmlspecialchars($s['staff_no']) ?></span></td>
                    <td><strong><?= htmlspecialchars($s['full_name']) ?></strong></td>
                    <td class="text-muted-c"><?= htmlspecialchars($s['job_title']) ?></td>
                    <td class="text-muted-c"><?= htmlspecialchars($s['workstation']) ?></td>
                    <td>
                        <span style="background:var(--accent-dim);color:var(--accent);font-size:11px;padding:2px 8px;border-radius:20px;">
                            <?= htmlspecialchars($s['department']) ?>
                        </span>
                    </td>
                    <td class="text-muted-c"><?= htmlspecialchars($s['email']) ?></td>
                    <td>
                        <!-- Edit button triggers modal with data -->
                        <button class="btn-edit-sm"
                            onclick="openEdit(<?= htmlspecialchars(json_encode($s)) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>

                        <!-- Delete form -->
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Delete <?= htmlspecialchars($s['full_name']) ?>? Their results will also be removed.')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id"     value="<?= $s['id'] ?>">
                            <button type="submit" class="btn-danger-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Export CSV link -->
<div class="mt-3">
    <a href="train_export.php?type=staff" class="btn-ghost">
        <i class="bi bi-download"></i> Export Staff CSV
    </a>
</div>


<!-- ===== ADD STAFF MODAL ===== -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
            <h5 class="modal-title">Add New Staff</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label">Staff No *</label>
                    <input type="text" name="staff_no" class="form-control" required placeholder="e.g. EMP001">
                </div>
                <div class="col-6">
                    <label class="form-label">Full Name (As Per NID) *</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="col-6">
                    <label class="form-label">Current Job Title</label>
                    <input type="text" name="job_title" class="form-control">
                </div>
                <div class="col-6">
                    <label class="form-label">Work Station</label>
                    <input type="text" name="workstation" class="form-control">
                </div>
                <div class="col-6">
                    <label class="form-label">Department</label>
                    <input type="text" name="department" class="form-control" list="deptList">
                    <datalist id="deptList">
                        <?php foreach ($departments as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-6">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-accent"><i class="bi bi-check-lg"></i> Add Staff</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- ===== EDIT STAFF MODAL ===== -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id"     id="edit_id">
        <div class="modal-header">
            <h5 class="modal-title">Edit Staff Record</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label">Staff No *</label>
                    <input type="text" name="staff_no" id="edit_staff_no" class="form-control" required>
                </div>
                <div class="col-6">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                </div>
                <div class="col-6">
                    <label class="form-label">Job Title</label>
                    <input type="text" name="job_title" id="edit_job_title" class="form-control">
                </div>
                <div class="col-6">
                    <label class="form-label">Work Station</label>
                    <input type="text" name="workstation" id="edit_workstation" class="form-control">
                </div>
                <div class="col-6">
                    <label class="form-label">Department</label>
                    <input type="text" name="department" id="edit_department" class="form-control">
                </div>
                <div class="col-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control">
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


<!-- ===== IMPORT CSV MODAL ===== -->
<div class="modal fade" id="importModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import_csv">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-upload me-2"></i>Import Staff from CSV</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <p style="font-size:13px;color:var(--muted);margin-bottom:16px;">
                Your CSV must have <strong>6 columns</strong> in this order:<br>
                <code style="color:var(--accent)">Staff No, Full Name, Job Title, Work Station, Department, Email</code><br><br>
                Row 1 is treated as a header and skipped. Duplicate Staff Nos are ignored.
            </p>
            <label class="form-label">Choose CSV File</label>
            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-accent"><i class="bi bi-cloud-upload"></i> Upload & Import</button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
// Populate the edit modal with a staff member's data
function openEdit(staff) {
    document.getElementById('edit_id').value         = staff.id;
    document.getElementById('edit_staff_no').value   = staff.staff_no;
    document.getElementById('edit_full_name').value  = staff.full_name;
    document.getElementById('edit_job_title').value  = staff.job_title || '';
    document.getElementById('edit_workstation').value= staff.workstation || '';
    document.getElementById('edit_department').value = staff.department || '';
    document.getElementById('edit_email').value      = staff.email || '';

    // Open the modal programmatically
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require 'footer.php'; ?>