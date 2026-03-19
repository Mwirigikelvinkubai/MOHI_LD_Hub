<?php
/**
 * manage_users.php — Hub User Management
 * Admin-only. Full CRUD for hub login accounts.
 * Roles: admin | editor | viewer
 */

require_once 'config.php';
require_once 'auth.php';

// Admin-only gate
requireAdmin();

$pageTitle  = 'Manage Users';
$activePage = 'manage_users';
$pdo        = getDB();
$me         = currentUser();

// ── Helpers ───────────────────────────────────────────────────────────

function countAdmins(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
}

function passwordError(string $pw, string $confirm): string {
    if (strlen($pw) < 8)           return 'Password must be at least 8 characters.';
    if ($pw !== $confirm)          return 'Passwords do not match.';
    if (!preg_match('/[A-Z]/', $pw)) return 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[0-9]/', $pw)) return 'Password must contain at least one number.';
    return '';
}

// ── POST handlers ─────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();

    // ── ADD USER ──────────────────────────────────────────────────────
    if ($_POST['action'] === 'add') {
        $username  = clean($_POST['username']);
        $full_name = clean($_POST['full_name']);
        $email     = clean($_POST['email']);
        $role      = in_array($_POST['role'], ['admin','editor','viewer']) ? $_POST['role'] : 'viewer';
        $pw        = $_POST['password']  ?? '';
        $pw2       = $_POST['password2'] ?? '';

        $err = passwordError($pw, $pw2);
        if ($err) {
            setFlash('danger', $err);
        } elseif (empty($username)) {
            setFlash('danger', 'Username is required.');
        } else {
            try {
                $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?,?,?,?,?)")
                    ->execute([$username, password_hash($pw, PASSWORD_BCRYPT), $full_name, $email, $role]);
                setFlash('success', "User <strong>{$username}</strong> created successfully.");
            } catch (PDOException $e) {
                setFlash('danger', 'That username is already taken. Please choose another.');
            }
        }
        redirect('manage_users.php');
    }

    // ── EDIT USER ─────────────────────────────────────────────────────
    if ($_POST['action'] === 'edit') {
        $id        = (int)$_POST['id'];
        $username  = clean($_POST['username']);
        $full_name = clean($_POST['full_name']);
        $email     = clean($_POST['email']);
        $role      = in_array($_POST['role'], ['admin','editor','viewer']) ? $_POST['role'] : 'viewer';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Safety: prevent last admin from losing admin role or being deactivated
        if ($id === (int)$me['id'] && $role !== 'admin') {
            setFlash('danger', 'You cannot remove admin role from your own account.');
            redirect('manage_users.php');
        }
        $target = $pdo->prepare("SELECT role FROM users WHERE id=?")->execute([$id]) && false;
        $targetRow = $pdo->prepare("SELECT role, is_active FROM users WHERE id=?")->execute([$id])
            ? $pdo->prepare("SELECT role, is_active FROM users WHERE id=?") : null;
        // Simpler approach:
        $tr = $pdo->prepare("SELECT role FROM users WHERE id=?");
        $tr->execute([$id]);
        $targetRole = $tr->fetchColumn();

        if ($targetRole === 'admin' && $role !== 'admin' && countAdmins($pdo) <= 1) {
            setFlash('danger', 'Cannot demote the only active admin.');
            redirect('manage_users.php');
        }
        if ($targetRole === 'admin' && !$is_active && countAdmins($pdo) <= 1) {
            setFlash('danger', 'Cannot deactivate the only active admin.');
            redirect('manage_users.php');
        }

        try {
            $pdo->prepare("UPDATE users SET username=?, full_name=?, email=?, role=?, is_active=? WHERE id=?")
                ->execute([$username, $full_name, $email, $role, $is_active, $id]);
            // Refresh session if editing self
            if ($id === (int)$me['id']) {
                $_SESSION['ld_hub_user'] = $username;
                $_SESSION['ld_hub_name'] = $full_name ?: $username;
                $_SESSION['ld_hub_role'] = $role;
            }
            setFlash('success', "User <strong>{$username}</strong> updated.");
        } catch (PDOException $e) {
            setFlash('danger', 'That username is already taken by another account.');
        }
        redirect('manage_users.php');
    }

    // ── RESET PASSWORD ────────────────────────────────────────────────
    if ($_POST['action'] === 'reset_password') {
        $id  = (int)$_POST['id'];
        $pw  = $_POST['password']  ?? '';
        $pw2 = $_POST['password2'] ?? '';

        $err = passwordError($pw, $pw2);
        if ($err) {
            setFlash('danger', $err);
        } else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($pw, PASSWORD_BCRYPT), $id]);
            $uname = $pdo->prepare("SELECT username FROM users WHERE id=?")->execute([$id])
                ? '' : '';
            $row = $pdo->prepare("SELECT username FROM users WHERE id=?");
            $row->execute([$id]);
            $uname = $row->fetchColumn();
            setFlash('success', "Password reset for <strong>{$uname}</strong>.");
        }
        redirect('manage_users.php');
    }

    // ── TOGGLE ACTIVE ─────────────────────────────────────────────────
    if ($_POST['action'] === 'toggle_active') {
        $id = (int)$_POST['id'];
        if ($id === (int)$me['id']) {
            setFlash('danger', 'You cannot deactivate your own account.');
            redirect('manage_users.php');
        }
        $row = $pdo->prepare("SELECT role, is_active FROM users WHERE id=?");
        $row->execute([$id]);
        $target = $row->fetch();
        if ($target['role'] === 'admin' && $target['is_active'] && countAdmins($pdo) <= 1) {
            setFlash('danger', 'Cannot deactivate the only active admin.');
            redirect('manage_users.php');
        }
        $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE id=?")->execute([$id]);
        redirect('manage_users.php');
    }

    // ── DELETE USER ───────────────────────────────────────────────────
    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        if ($id === (int)$me['id']) {
            setFlash('danger', 'You cannot delete your own account.');
            redirect('manage_users.php');
        }
        $row = $pdo->prepare("SELECT username, role FROM users WHERE id=?");
        $row->execute([$id]);
        $target = $row->fetch();
        if (!$target) { redirect('manage_users.php'); }

        if ($target['role'] === 'admin' && countAdmins($pdo) <= 1) {
            setFlash('danger', 'Cannot delete the only admin account.');
            redirect('manage_users.php');
        }
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        setFlash('success', "User <strong>{$target['username']}</strong> deleted.");
        redirect('manage_users.php');
    }
}

// ── Fetch all users ───────────────────────────────────────────────────
$users = $pdo->query("SELECT * FROM users ORDER BY role ASC, username ASC")->fetchAll();

require 'header.php';
?>

<!-- Page header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <div class="section-title">Manage Users</div>
        <div class="section-sub"><?= count($users) ?> hub account<?= count($users) !== 1 ? 's' : '' ?> · Admin-only area</div>
    </div>
    <button class="btn-accent" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-person-plus-fill"></i> Add User
    </button>
</div>

<!-- Role legend -->
<div class="d-flex gap-3 mb-4 flex-wrap" style="font-size:12px;">
    <span><span class="role-badge admin">Admin</span> &nbsp;Full access + user management</span>
    <span><span class="role-badge editor">Editor</span> &nbsp;Add / edit / delete data</span>
    <span><span class="role-badge viewer">Viewer</span> &nbsp;Read-only access</span>
</div>

<!-- Users table -->
<div class="card">
    <div class="table-responsive">
        <table class="table datatable" id="usersTable">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td>
                    <strong style="color:var(--accent)"><?= htmlspecialchars($u['username']) ?></strong>
                    <?php if ((int)$u['id'] === (int)$me['id']): ?>
                        <span style="font-size:10px;color:var(--muted);margin-left:5px">(you)</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($u['full_name'] ?: '—') ?></td>
                <td class="text-muted-c" style="font-size:12px"><?= htmlspecialchars($u['email'] ?: '—') ?></td>
                <td><span class="role-badge <?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                <td>
                    <?php if ($u['is_active']): ?>
                        <span class="badge-status active">Active</span>
                    <?php else: ?>
                        <span class="badge-status behind">Inactive</span>
                    <?php endif; ?>
                </td>
                <td class="text-muted-c" style="font-size:12px">
                    <?= $u['last_login'] ? date('d M Y, H:i', strtotime($u['last_login'])) : 'Never' ?>
                </td>
                <td class="text-muted-c" style="font-size:12px">
                    <?= date('d M Y', strtotime($u['created_at'])) ?>
                </td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">
                        <!-- Edit -->
                        <button class="btn-edit-sm" title="Edit user"
                            onclick="openEdit(<?= htmlspecialchars(json_encode($u)) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>

                        <!-- Reset password -->
                        <button class="btn-edit-sm" title="Reset password"
                            onclick="openResetPw(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                            <i class="bi bi-key"></i>
                        </button>

                        <!-- Toggle active -->
                        <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn-edit-sm" title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                <i class="bi bi-<?= $u['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i>
                            </button>
                        </form>
                        <?php endif; ?>

                        <!-- Delete -->
                        <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Delete user <?= htmlspecialchars($u['username'], ENT_QUOTES) ?>? This cannot be undone.')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn-danger-sm" title="Delete user">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Default credentials notice if admin hasn't changed password -->
<div class="alert alert-info mt-3" style="font-size:12.5px;">
    <i class="bi bi-shield-lock me-2"></i>
    <strong>Default credentials:</strong> username <code>admin</code> · password <code>Admin@2025!</code>
    — Change via the <i class="bi bi-key"></i> reset password button above after first login.
</div>


<!-- ═══════════════════════════════════════════
     ADD USER MODAL
══════════════════════════════════════════════ -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" id="addForm">
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" required
                           placeholder="e.g. jdoe" autocomplete="off"
                           pattern="[a-zA-Z0-9_\-\.]+" title="Letters, numbers, _ - . only">
                </div>
                <div class="col-6">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" placeholder="Jane Doe">
                </div>
                <div class="col-12">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="jane@mohi.org">
                </div>
                <div class="col-12">
                    <label class="form-label">Role *</label>
                    <select name="role" class="form-select" required>
                        <option value="viewer">Viewer — read-only access</option>
                        <option value="editor">Editor — add / edit / delete data</option>
                        <option value="admin">Admin — full access + user management</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label">Password *</label>
                    <div class="pw-wrap">
                        <input type="password" name="password" id="add_pw" class="form-control" required
                               autocomplete="new-password" minlength="8">
                        <button type="button" class="toggle-eye" onclick="toggleFieldPw('add_pw','add_eye')">
                            <i class="bi bi-eye" id="add_eye"></i>
                        </button>
                    </div>
                </div>
                <div class="col-6">
                    <label class="form-label">Confirm Password *</label>
                    <div class="pw-wrap">
                        <input type="password" name="password2" id="add_pw2" class="form-control" required
                               autocomplete="new-password" minlength="8">
                        <button type="button" class="toggle-eye" onclick="toggleFieldPw('add_pw2','add_eye2')">
                            <i class="bi bi-eye" id="add_eye2"></i>
                        </button>
                    </div>
                </div>
                <div class="col-12">
                    <div class="pw-rules">
                        <i class="bi bi-info-circle"></i>
                        Min 8 chars · at least 1 uppercase · at least 1 number
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-accent"><i class="bi bi-check-lg"></i> Create User</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- ═══════════════════════════════════════════
     EDIT USER MODAL
══════════════════════════════════════════════ -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-person-gear me-2"></i>Edit User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" id="edit_username" class="form-control" required
                           pattern="[a-zA-Z0-9_\-\.]+" title="Letters, numbers, _ - . only">
                </div>
                <div class="col-6">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" id="edit_full_name" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" id="edit_email" class="form-control">
                </div>
                <div class="col-8">
                    <label class="form-label">Role *</label>
                    <select name="role" id="edit_role" class="form-select">
                        <option value="viewer">Viewer</option>
                        <option value="editor">Editor</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="col-4 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1">
                        <label class="form-check-label" for="edit_is_active" style="color:var(--text);font-size:13px">Active</label>
                    </div>
                </div>
            </div>
            <div class="alert-info mt-3" style="font-size:12px;border-radius:6px;padding:8px 12px;">
                <i class="bi bi-key me-1"></i> To change this user's password, use the <strong>Reset Password</strong> button on the table.
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


<!-- ═══════════════════════════════════════════
     RESET PASSWORD MODAL
══════════════════════════════════════════════ -->
<div class="modal fade" id="resetPwModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="id" id="rpw_id">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-key me-2"></i>Reset Password</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <p style="font-size:13px;color:var(--muted);margin-bottom:16px">
                Resetting password for: <strong id="rpw_username" style="color:var(--text)"></strong>
            </p>
            <div class="mb-3">
                <label class="form-label">New Password *</label>
                <div class="pw-wrap">
                    <input type="password" name="password" id="rpw_pw" class="form-control" required
                           autocomplete="new-password" minlength="8">
                    <button type="button" class="toggle-eye" onclick="toggleFieldPw('rpw_pw','rpw_eye')">
                        <i class="bi bi-eye" id="rpw_eye"></i>
                    </button>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label">Confirm New Password *</label>
                <div class="pw-wrap">
                    <input type="password" name="password2" id="rpw_pw2" class="form-control" required
                           autocomplete="new-password" minlength="8">
                    <button type="button" class="toggle-eye" onclick="toggleFieldPw('rpw_pw2','rpw_eye2')">
                        <i class="bi bi-eye" id="rpw_eye2"></i>
                    </button>
                </div>
            </div>
            <div class="pw-rules"><i class="bi bi-info-circle"></i> Min 8 chars · 1 uppercase · 1 number</div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-accent"><i class="bi bi-key"></i> Reset</button>
        </div>
      </form>
    </div>
  </div>
</div>


<style>
/* ── Role badges ─────────────────────────── */
.role-badge {
    display: inline-block;
    font-size: 10.5px;
    font-weight: 700;
    font-family: 'Barlow', sans-serif;
    padding: 2px 10px;
    border-radius: 20px;
    white-space: nowrap;
}
.role-badge.admin  { background: rgba(38,169,224,0.15); color: #26A9E0; border: 1px solid rgba(38,169,224,0.3); }
.role-badge.editor { background: rgba(139,197,63,0.12); color: #8BC53F; border: 1px solid rgba(139,197,63,0.3); }
.role-badge.viewer { background: rgba(122,170,200,0.1); color: #9aabb8; border: 1px solid rgba(122,170,200,0.2); }

/* ── Password field wrapper ──────────────── */
.pw-wrap { position: relative; }
.pw-wrap .form-control { padding-right: 36px; }
.pw-wrap .toggle-eye {
    position: absolute; right: 10px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    color: var(--muted); font-size: 14px; padding: 0; line-height: 1;
}
.pw-wrap .toggle-eye:hover { color: var(--text); }

/* ── Password rules hint ─────────────────── */
.pw-rules {
    font-size: 11.5px;
    color: var(--muted);
    background: rgba(38,169,224,0.06);
    border: 1px solid rgba(38,169,224,0.15);
    border-radius: 6px;
    padding: 7px 12px;
}
</style>

<script>
function openEdit(u) {
    document.getElementById('edit_id').value        = u.id;
    document.getElementById('edit_username').value  = u.username;
    document.getElementById('edit_full_name').value = u.full_name || '';
    document.getElementById('edit_email').value     = u.email    || '';
    document.getElementById('edit_role').value      = u.role;
    document.getElementById('edit_is_active').checked = u.is_active == 1;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function openResetPw(id, username) {
    document.getElementById('rpw_id').value       = id;
    document.getElementById('rpw_username').textContent = username;
    document.getElementById('rpw_pw').value       = '';
    document.getElementById('rpw_pw2').value      = '';
    new bootstrap.Modal(document.getElementById('resetPwModal')).show();
}

function toggleFieldPw(fieldId, eyeId) {
    const f = document.getElementById(fieldId);
    const e = document.getElementById(eyeId);
    f.type   = f.type === 'password' ? 'text' : 'password';
    e.className = f.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>

<?php require 'footer.php'; ?>