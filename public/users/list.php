<?php
// ============================================================
// CIRMS – User Management
// public/users/list.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();
require_login(['admin']);

$pdo = db();

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $uid    = (int)($_POST['user_id'] ?? 0);

    if ($action === 'toggle_active' && $uid) {
        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $cur = $stmt->fetchColumn();
        $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([!$cur, $uid]);
        $action_label = $cur ? 'deactivated' : 'activated';
        audit_log("user.$action_label", 'user', $uid);
        flash('success', "User account {$action_label}.");
    }

    if ($action === 'change_role' && $uid) {
        $newRole = $_POST['role'] ?? '';
        $validRoles = ['reporter', 'officer', 'admin'];
        if (in_array($newRole, $validRoles, true) && $uid !== (int)current_user()['id']) {
            $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $uid]);
            audit_log('user.role_changed', 'user', $uid, ['new_role' => $newRole]);
            flash('success', 'User role updated.');
        }
    }

    if ($action === 'create_user') {
        $name  = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role  = $_POST['role']  ?? 'reporter';
        $pass  = $_POST['password'] ?? '';
        $dept  = trim($_POST['department'] ?? '');
        $validRoles = ['reporter', 'officer', 'admin'];

        $errs = [];
        if (strlen($name) < 3)                           $errs[] = 'Name too short.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errs[] = 'Invalid email.';
        if (!in_array($role, $validRoles, true))          $errs[] = 'Invalid role.';
        if (strlen($pass) < 8)                           $errs[] = 'Password must be ≥ 8 chars.';

        if (empty($errs)) {
            $check = $pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
            $check->execute([$email]);
            if ($check->fetch()) {
                $errs[] = 'Email already in use.';
            }
        }

        if (empty($errs)) {
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $pwCol = users_password_column_sql();
            $pdo->prepare("
                INSERT INTO users (full_name, email, {$pwCol}, role, department)
                VALUES (?,?,?,?,?)
            ")->execute([$name, $email, $hash, $role, $dept]);
            $newId = (int)$pdo->lastInsertId();
            audit_log('user.created', 'user', $newId, ['email' => $email, 'role' => $role]);
            flash('success', "User account created for $email.");
        } else {
            flash('error', implode(' ', $errs));
        }
    }

    redirect('/public/users/list.php');
}

// ── Fetch users ──────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$filter = $_GET['role'] ?? '';

$where  = ['1=1'];
$params = [];
if ($search) { $where[] = '(full_name LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filter) { $where[] = 'role = ?'; $params[] = $filter; }

$stmt = $pdo->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM incidents WHERE reporter_id = u.id) AS incident_count
    FROM users u
    WHERE " . implode(' AND ', $where) . "
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-people-fill me-2 text-cyan"></i>Manage Users</h1>
        <p class="page-subtitle"><?= count($users) ?> accounts</p>
    </div>
    <button class="btn btn-dark btn-cirms btn-primary-cirms"
            data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="bi bi-person-plus me-1"></i> Create User
    </button>
</div>

<!-- Filter bar -->
<div class="cirms-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
            <input type="text" name="q" class="form-control" placeholder="Search name or email…"
                   value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
            <select name="role" class="form-select">
                <option value="">All roles</option>
                <option value="reporter" <?= $filter==='reporter'?'selected':'' ?>>Reporter</option>
                <option value="officer"  <?= $filter==='officer' ?'selected':'' ?>>IT Officer</option>
                <option value="admin"    <?= $filter==='admin'   ?'selected':'' ?>>Admin</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-dark w-100"><i class="bi bi-search me-1"></i>Filter</button>
        </div>
        <div class="col-md-2">
            <a href="<?= APP_URL ?>/public/users/list.php" class="btn btn-outline-secondary w-100">Clear</a>
        </div>
    </form>
</div>

<!-- Users Table -->
<div class="cirms-card">
    <div class="table-responsive">
        <table class="cirms-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Incidents</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td style="font-weight:600;font-size:.875rem;"><?= e($u['full_name']) ?></td>
                <td style="font-size:.85rem;color:#64748b;"><?= e($u['email']) ?></td>
                <td>
                    <span class="role-chip role-<?= e($u['role']) ?>"><?= ucfirst(e($u['role'])) ?></span>
                </td>
                <td style="font-size:.85rem;"><?= e($u['department'] ?: '—') ?></td>
                <td style="font-size:.85rem;text-align:center;"><?= $u['incident_count'] ?></td>
                <td>
                    <?php if ($u['is_active']): ?>
                    <span style="color:#16a34a;font-size:.8rem;font-weight:600;">● Active</span>
                    <?php else: ?>
                    <span style="color:#94a3b8;font-size:.8rem;font-weight:600;">● Inactive</span>
                    <?php endif; ?>
                </td>
                <td class="text-muted" style="font-size:.8rem;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <div class="d-flex gap-1">
                        <!-- Role change -->
                        <?php if ($u['id'] !== (int)current_user()['id']): ?>
                        <form method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="change_role">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <select name="role" class="form-select form-select-sm"
                                    onchange="this.form.submit()" style="font-size:.75rem;padding:.2rem .5rem;">
                                <option value="reporter" <?= $u['role']==='reporter'?'selected':'' ?>>Reporter</option>
                                <option value="officer"  <?= $u['role']==='officer' ?'selected':'' ?>>Officer</option>
                                <option value="admin"    <?= $u['role']==='admin'   ?'selected':'' ?>>Admin</option>
                            </select>
                        </form>

                        <!-- Toggle active -->
                        <form method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                    data-confirm="<?= $u['is_active'] ? 'Deactivate this user?' : 'Reactivate this user?' ?>">
                                <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:.78rem;">(you)</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title" style="font-family:'Space Mono',monospace;font-weight:700;">
                    Create User Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_user">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">Role *</label>
                            <select name="role" class="form-select">
                                <option value="reporter">Reporter</option>
                                <option value="officer">IT Officer</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Department</label>
                            <input type="text" name="department" class="form-control">
                        </div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Temporary Password *</label>
                        <input type="password" name="password" class="form-control"
                               placeholder="Min. 8 characters" required>
                        <div class="form-hint">User should change this on first login.</div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
