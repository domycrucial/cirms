<?php
// ============================================================
// CIRMS – User Account Management
// public/users/list.php
//
// EMAILS SENT FROM THIS FILE (4 types):
//
//   EMAIL 1 → New User (on account creation)
//             Function: notify_account_created()
//             Trigger:  Admin creates account via the modal form
//             Content:  Login email, temp password, role, login link
//
//   EMAIL 2 → User (on account reactivation only)
//             Function: notify_account_activated()
//             Trigger:  Admin clicks Activate on a disabled account
//             Content:  Confirmation that access has been restored
//             NOTE:     No email sent on DEACTIVATION — the account
//                       is disabled so the email would be pointless.
//
//   EMAIL 3 → User (on role change)
//             Function: notify_role_changed()
//             Trigger:  Admin changes role via the dropdown
//             Content:  Old role, new role, description of new permissions
//
//   EMAIL 4 → User (on lockout cleared)
//             Function: notify_lockout_cleared()
//             Trigger:  Admin clicks Unlock on a locked account
//             Content:  Lockout lifted, sign-in link, security notice
//
// All email functions live in modules/notifications/mailer.php.
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';

// Load mailer.php — required for all three notify_* calls below
require_once __DIR__ . '/../../modules/notifications/mailer.php';

session_start_secure();
require_login(['admin']); // only admins can manage users

$pdo = db();

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = $_POST['action'] ?? '';
    $uid    = (int)($_POST['user_id'] ?? 0);

    // ══════════════════════════════════════════════════════════
    //  ACTION 1: Toggle account active/inactive
    // ══════════════════════════════════════════════════════════
    if ($action === 'toggle_active' && $uid) {

        // Fetch the user's current is_active value and their details
        $stmt = $pdo->prepare("SELECT is_active, full_name, email FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $targetUser = $stmt->fetch();

        if ($targetUser) {
            // Flip the active flag: 1 → 0 (deactivate) or 0 → 1 (activate)
            $newActive = $targetUser['is_active'] ? 0 : 1;
            $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$newActive, $uid]);

            $action_label = $newActive ? 'activated' : 'deactivated';
            audit_log("user.{$action_label}", 'user', $uid);

            // ── EMAIL: Only notify the user when ACTIVATING ───
            // Deactivated accounts cannot receive emails usefully.
            // We only email when re-enabling access.
            if ($newActive === 1) {
                notify_account_activated(
                    $targetUser['email'],     // user's email address
                    $targetUser['full_name']  // user's full name for salutation
                );
                flash('success', "Account activated. {$targetUser['full_name']} has been notified by email.");
            } else {
                flash('success', "Account deactivated for {$targetUser['full_name']}.");
            }
        }
    }

    // ══════════════════════════════════════════════════════════
    //  ACTION 2: Change user role
    // ══════════════════════════════════════════════════════════
    if ($action === 'change_role' && $uid) {

        $newRole    = $_POST['role'] ?? '';
        $validRoles = ['reporter', 'officer', 'admin'];

        // Validate role value and prevent admin from changing their own role
        if (in_array($newRole, $validRoles, true) && $uid !== (int)current_user()['id']) {

            // Fetch the user's current role and details before updating
            $stmt = $pdo->prepare("SELECT role, full_name, email FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $targetUser = $stmt->fetch();

            if ($targetUser && $targetUser['role'] !== $newRole) {
                // Only update and notify if the role actually changed
                $oldRole = $targetUser['role'];

                $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $uid]);
                audit_log('user.role_changed', 'user', $uid, [
                    'from' => $oldRole,
                    'to'   => $newRole,
                ]);

                // ── EMAIL: Tell user their access level changed ──
                // notify_role_changed() explains the new role and
                // what features/permissions the user now has access to.
                notify_role_changed(
                    $targetUser['email'],     // user's email address
                    $targetUser['full_name'], // user's full name
                    $oldRole,                 // previous role (e.g. 'reporter')
                    $newRole                  // new role (e.g. 'officer')
                );

                flash('success', "Role changed to " . ucfirst($newRole) . ". {$targetUser['full_name']} has been notified.");
            } else {
                flash('success', 'Role is already set to that value — no change made.');
            }
        }
    }

    // ══════════════════════════════════════════════════════════
    //  ACTION 3: Create a new user account
    // ══════════════════════════════════════════════════════════
    if ($action === 'create_user') {

        $name  = trim($_POST['full_name']   ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role  = $_POST['role']             ?? 'reporter';
        $pass  = $_POST['password']         ?? '';
        $dept  = trim($_POST['department']  ?? '');

        $validRoles = ['reporter', 'officer', 'admin'];

        // Validate all required fields
        $errs = [];
        if (strlen($name) < 3)                          $errs[] = 'Full name must be at least 3 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errs[] = 'Please enter a valid email address.';
        if (!in_array($role, $validRoles, true))         $errs[] = 'Please select a valid role.';
        if (strlen($pass) < 8)                          $errs[] = 'Password must be at least 8 characters.';

        // Check email is not already registered
        if (empty($errs)) {
            $check = $pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
            $check->execute([$email]);
            if ($check->fetch()) {
                $errs[] = 'An account with this email address already exists.';
            }
        }

        if (empty($errs)) {

            // Hash the password with bcrypt — never store plain text
            $hash  = password_hash($pass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $pwCol = users_password_column_sql();

            $pdo->prepare("
                INSERT INTO users (full_name, email, {$pwCol}, role, department)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$name, $email, $hash, $role, $dept]);

            $newId = (int) $pdo->lastInsertId();
            audit_log('user.created', 'user', $newId, ['email' => $email, 'role' => $role]);

            // ── EMAIL: Send the new user their login details ──
            //
            // notify_account_created() sends:
            //   - Their login email
            //   - The temporary password (plain text, one time only)
            //   - Their role and what it means
            //   - A login link button to CIRMS
            //
            // We pass $pass (the plain text password) here because the
            // user needs to know it to log in for the first time.
            // After this, the password is only stored as a bcrypt hash.
            notify_account_created(
                $email,  // new user's email address
                $name,   // new user's full name
                $role,   // their assigned role
                $pass    // plain text password — only used in this one welcome email
            );

            flash('success', "Account created for {$name}. Login details have been emailed to {$email}.");

        } else {
            flash('error', implode(' ', $errs));
        }
    }

    // ══════════════════════════════════════════════════════════
    //  ACTION 4: Unlock a locked-out user account
    //
    //  Inserts an 'auth.lockout_cleared' event into audit_log.
    //  login.php only counts failures that happened AFTER the
    //  latest lockout_cleared event, so this takes effect
    //  instantly — no waiting for the 30-minute window.
    // ══════════════════════════════════════════════════════════
    if ($action === 'unlock_user' && $uid) {

        $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $targetUser = $stmt->fetch();

        if ($targetUser) {
            // Write the clear marker into audit_log so login.php skips
            // any failures that predate this timestamp.
            audit_log('auth.lockout_cleared', 'user', $uid, [
                'email'      => $targetUser['email'],
                'cleared_by' => 'admin',
            ]);

            notify_lockout_cleared($targetUser['email'], $targetUser['full_name']);

            flash('success',
                "Lockout cleared for {$targetUser['full_name']}. " .
                "They have been notified by email and may now sign in."
            );
        }
    }

    redirect('/public/users/list.php');
}

// ── Fetch users with search, role, and locked filter ─────────
$search      = trim($_GET['q']      ?? '');
$filter      = $_GET['role']        ?? '';
$filterLocked= isset($_GET['locked']) && $lockedEmails; // show locked-only

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(full_name LIKE ? OR email LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($filter) {
    $where[]  = 'role = ?';
    $params[] = $filter;
}

if ($filterLocked) {
    // Build an IN list of locked email addresses
    $lockedPlaceholders = implode(',', array_fill(0, count($lockedEmails), '?'));
    $where[]  = "email IN ({$lockedPlaceholders})";
    $params   = array_merge($params, array_keys($lockedEmails));
}

$stmt = $pdo->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM incidents WHERE reporter_id = u.id) AS incident_count
    FROM   users u
    WHERE  " . implode(' AND ', $where) . "
    ORDER  BY u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// ── Detect currently locked accounts ────────────────────────
// An account is "locked" when it has ≥3 auth.login_failed events
// in the last 30 minutes that all occurred AFTER the most recent
// auth.lockout_cleared event (or no clear event exists).
//
// Step 1 — pull all emails with ≥3 recent failures.
$rawFails = $pdo->query("
    SELECT JSON_UNQUOTE(JSON_EXTRACT(details, '$.email')) AS email,
           COUNT(*)        AS fail_count,
           MAX(created_at) AS last_fail
    FROM   audit_log
    WHERE  action     = 'auth.login_failed'
      AND  created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    GROUP  BY email
    HAVING fail_count >= 3
")->fetchAll(PDO::FETCH_ASSOC);

// Step 2 — for each, verify no admin-clear event came AFTER the failures.
// (If cleared_at > last_fail the admin already unlocked this session.)
$lockedEmails = [];
foreach ($rawFails as $row) {
    $em = strtolower(trim($row['email'] ?? ''));
    if (!$em) continue;

    $clrStmt = $pdo->prepare("
        SELECT MAX(created_at) FROM audit_log
        WHERE  action = 'auth.lockout_cleared'
          AND  details LIKE ?
    ");
    $clrStmt->execute(['%' . json_encode($em) . '%']);
    $lastClear = $clrStmt->fetchColumn();

    // Still locked when: never cleared, OR the clear happened before the failures
    if (!$lastClear || $lastClear < $row['last_fail']) {
        $lockedEmails[$em] = [
            'fail_count' => (int) $row['fail_count'],
            'last_fail'  => $row['last_fail'],
            'expires_at' => date('H:i', strtotime($row['last_fail'] . ' +30 minutes')),
        ];
    }
}

$pageTitle = 'Manage Users';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-people-fill me-2 text-cyan"></i>Manage Users
        </h1>
        <p class="page-subtitle">
            <?= count($users) ?> account<?= count($users) !== 1 ? 's' : '' ?>
            <?php if ($lockedEmails): ?>
                &nbsp;·&nbsp;
                <span style="color:#ef4444;font-weight:600;">
                    <i class="bi bi-lock-fill"></i>
                    <?= count($lockedEmails) ?> locked
                </span>
            <?php endif; ?>
        </p>
    </div>
    <!-- Opens the create-user modal -->
    <button class="btn btn-dark btn-cirms btn-primary-cirms"
            data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="bi bi-person-plus me-1"></i> Create User
    </button>
</div>

<!-- Flash message display -->
<?php $flash = get_flash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-3">
    <?= e($flash['message']) ?>
</div>
<?php endif; ?>

<!-- Locked accounts alert banner -->
<?php if ($lockedEmails): ?>
<div class="alert mb-3" style="background:#fff5f5;border:1.5px solid #fca5a5;color:#7f1d1d;border-radius:10px;">
    <div class="d-flex align-items-start gap-2">
        <i class="bi bi-shield-exclamation fs-5 mt-1" style="color:#ef4444;flex-shrink:0;"></i>
        <div>
            <strong><?= count($lockedEmails) ?> account<?= count($lockedEmails) !== 1 ? 's are' : ' is' ?> currently locked</strong>
            due to repeated failed sign-in attempts. Review and unlock below if the attempts were legitimate.
            <div class="mt-2 d-flex flex-wrap gap-2">
                <?php foreach ($lockedEmails as $em => $info): ?>
                <span style="background:#fee2e2;border-radius:6px;padding:.2rem .6rem;font-size:.8rem;font-weight:600;">
                    <i class="bi bi-lock-fill me-1"></i><?= e($em) ?>
                    &nbsp;<span style="font-weight:400;opacity:.75;">(<?= $info['fail_count'] ?> attempts · auto-expires <?= e($info['expires_at']) ?>)</span>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Search and filter bar -->
<div class="cirms-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
            <input type="text" name="q" class="form-control"
                   placeholder="Search name or email…"
                   value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
            <select name="role" class="form-select">
                <option value="">All roles</option>
                <option value="reporter" <?= $filter === 'reporter' ? 'selected' : '' ?>>Reporter</option>
                <option value="officer"  <?= $filter === 'officer'  ? 'selected' : '' ?>>IT Officer</option>
                <option value="admin"    <?= $filter === 'admin'    ? 'selected' : '' ?>>Admin</option>
            </select>
        </div>
        <?php if ($lockedEmails): ?>
        <div class="col-md-2">
            <a href="?locked=1" class="btn btn-outline-warning w-100"
               style="font-size:.82rem;"
               title="Show only locked accounts">
                <i class="bi bi-lock-fill me-1"></i>Locked (<?= count($lockedEmails) ?>)
            </a>
        </div>
        <?php endif; ?>
        <div class="col-md-2">
            <button class="btn btn-dark w-100">
                <i class="bi bi-search me-1"></i> Filter
            </button>
        </div>
        <div class="col-md-2">
            <a href="<?= APP_URL ?>/public/users/list.php" class="btn btn-outline-secondary w-100">
                Clear
            </a>
        </div>
    </form>
</div>

<!-- Users table -->
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
                    <span class="role-chip role-<?= e($u['role']) ?>">
                        <?= ucfirst(e($u['role'])) ?>
                    </span>
                </td>
                <td style="font-size:.85rem;"><?= e($u['department'] ?: '—') ?></td>
                <td style="font-size:.85rem;text-align:center;"><?= $u['incident_count'] ?></td>
                <td>
                    <?php
                        $isLocked = isset($lockedEmails[strtolower($u['email'])]);
                        $lockInfo = $isLocked ? $lockedEmails[strtolower($u['email'])] : null;
                    ?>
                    <?php if ($u['is_active']): ?>
                        <span style="color:#16a34a;font-size:.8rem;font-weight:600;">● Active</span>
                    <?php else: ?>
                        <span style="color:#94a3b8;font-size:.8rem;font-weight:600;">● Inactive</span>
                    <?php endif; ?>
                    <?php if ($isLocked): ?>
                    <br>
                    <span title="<?= $lockInfo['fail_count'] ?> failed attempts · auto-expires <?= e($lockInfo['expires_at']) ?>"
                          style="display:inline-flex;align-items:center;gap:.25rem;margin-top:.2rem;
                                 background:#fee2e2;color:#b91c1c;border-radius:5px;
                                 padding:.1rem .45rem;font-size:.73rem;font-weight:700;cursor:default;">
                        <i class="bi bi-lock-fill"></i> Locked
                    </span>
                    <?php endif; ?>
                </td>
                <td class="text-muted" style="font-size:.8rem;">
                    <?= date('d M Y', strtotime($u['created_at'])) ?>
                </td>
                <td>
                    <?php if ($u['id'] !== (int)current_user()['id']): ?>
                    <div class="d-flex gap-1 flex-wrap">

                        <!-- Role change dropdown — fires change_role action and sends email -->
                        <form method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action"  value="change_role">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <select name="role" class="form-select form-select-sm"
                                    onchange="this.form.submit()"
                                    style="font-size:.75rem;padding:.2rem .5rem;"
                                    title="Change role — will email the user">
                                <option value="reporter" <?= $u['role'] === 'reporter' ? 'selected' : '' ?>>Reporter</option>
                                <option value="officer"  <?= $u['role'] === 'officer'  ? 'selected' : '' ?>>Officer</option>
                                <option value="admin"    <?= $u['role'] === 'admin'    ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </form>

                        <!-- Activate / Deactivate button — sends email only on activation -->
                        <form method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action"  value="toggle_active">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit"
                                    class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                    title="<?= $u['is_active'] ? 'Deactivate account' : 'Reactivate account — will email user' ?>"
                                    data-confirm="<?= $u['is_active'] ? 'Deactivate this user account?' : 'Reactivate this account? The user will be notified by email.' ?>">
                                <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>

                        <?php if ($isLocked): ?>
                        <!-- Unlock button — clears failed-login lockout immediately -->
                        <form method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action"  value="unlock_user">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit"
                                    class="btn btn-sm btn-warning"
                                    style="font-size:.73rem;"
                                    title="Clear login lockout — <?= $lockInfo['fail_count'] ?> failed attempts. User will be notified by email."
                                    data-confirm="Clear the login lockout for <?= e($u['full_name']) ?>? They will be emailed to confirm access is restored.">
                                <i class="bi bi-unlock-fill me-1"></i>Unlock
                            </button>
                        </form>
                        <?php endif; ?>

                    </div>
                    <?php else: ?>
                        <span class="text-muted" style="font-size:.78rem;">(your account)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="createModalLabel"
                    style="font-family:'Space Mono',monospace;font-weight:700;">
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
                        <input type="text" name="full_name" class="form-control"
                               placeholder="e.g. Amina Hassan" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" class="form-control"
                               placeholder="user@university.ac.tz" required>
                        <div class="form-hint">Login credentials will be sent to this address.</div>
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
                            <input type="text" name="department" class="form-control"
                                   placeholder="e.g. ICT, Finance">
                        </div>
                    </div>

                    <div class="mb-1">
                        <label class="form-label">Temporary Password *</label>
                        <input type="password" name="password" class="form-control"
                               placeholder="Minimum 8 characters" required>
                        <div class="form-hint">
                            The user will receive this password by email. They should change it on first login.
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">
                        <i class="bi bi-person-check me-1"></i> Create &amp; Send Email
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
