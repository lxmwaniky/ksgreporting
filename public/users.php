<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use KSG\Auth;
use KSG\Database;

Auth::startSession();
Auth::requireLogin();

$currentUser = Auth::currentUser();
if (!in_array($currentUser['role'], ['admin', 'hod', 'deputy_director', 'director'])) {
    http_response_code(403);
    die('Access denied.');
}

$db = Database::getInstance()->getPdo();

$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error']   ?? null;
unset($_SESSION['success'], $_SESSION['error']);

if (in_array($currentUser['role'], ['deputy_director', 'director'])) {

    $campus = $currentUser['campus'];

    $stmt = $db->prepare("
        SELECT id, name, email, department, designation, is_active
        FROM users
        WHERE campus = :campus AND role = 'hod' AND is_active = 1
        ORDER BY department ASC, name ASC
    ");
    $stmt->execute([':campus' => $campus]);
    $hods = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT id, name, email, department, designation, is_active
        FROM users
        WHERE campus = :campus AND role = 'staff' AND is_active = 1
        ORDER BY department ASC, name ASC
    ");
    $stmt->execute([':campus' => $campus]);
    $allStaff = $stmt->fetchAll();

    $staffByDept = [];
    foreach ($allStaff as $s) {
        $staffByDept[$s['department']][] = $s;
    }

    $pageTitle = 'Campus Users';
    require_once __DIR__ . '/../templates/header.php';
    ?>

    <div class="container">
        <div class="page-header">
            <div class="page-header-content">
                <h1>Campus Users</h1>
                <span class="scope-notice" style="margin:0;">
                    <?= htmlspecialchars(CAMPUSES[$campus] ?? $campus, ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
        </div>

        <?php if (empty($hods)): ?>
            <div class="empty-state"><p>No HoD / HoS / Team Leaders found for this campus.</p></div>
        <?php else: ?>
            <div class="hod-tree">
                <?php foreach ($hods as $hod): ?>
                <div class="hod-block card" style="margin-bottom:1.25rem;">
                    <div class="hod-header" onclick="toggleStaff(<?= (int)$hod['id'] ?>)"
                         style="cursor:pointer; display:flex; justify-content:space-between; align-items:center; padding:1rem;">
                        <div>
                            <strong><?= htmlspecialchars($hod['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if (!empty($hod['designation'])): ?>
                                <span style="color:#888; font-size:0.875rem;">
                                    — <?= htmlspecialchars($hod['designation'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>
                            <br>
                            <small style="color:#888;">
                                <?= htmlspecialchars(DEPARTMENTS[$hod['department']] ?? $hod['department'], ENT_QUOTES, 'UTF-8') ?>
                                &middot; <?= htmlspecialchars($hod['email'], ENT_QUOTES, 'UTF-8') ?>
                            </small>
                        </div>
                        <span class="toggle-icon" id="icon-<?= (int)$hod['id'] ?>"
                              style="font-size:1.25rem; color:var(--ksg-brown);">&#43;</span>
                    </div>

                    <div class="staff-list" id="staff-<?= (int)$hod['id'] ?>" style="display:none; padding:0 1rem 1rem;">
                        <?php $deptStaff = $staffByDept[$hod['department']] ?? []; ?>
                        <?php if (empty($deptStaff)): ?>
                            <p style="color:#888; font-size:0.875rem;">No staff found in this department.</p>
                        <?php else: ?>
                            <table class="list-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Designation</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deptStaff as $i => $s): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($s['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($s['designation'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function toggleStaff(id) {
        const list = document.getElementById('staff-' + id);
        const icon = document.getElementById('icon-' + id);
        if (list.style.display === 'none') {
            list.style.display = 'block';
            icon.innerHTML = '&#8722;';
        } else {
            list.style.display = 'none';
            icon.innerHTML = '&#43;';
        }
    }
    </script>

    <?php
    require_once __DIR__ . '/../templates/footer.php';
    exit;
}

// ── Admin / HoD full management view ─────────────────────────────────────────

$filters = [
    'campus' => $_GET['campus'] ?? '',
    'role'   => $_GET['role']   ?? '',
    'status' => $_GET['status'] ?? '',
];

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($currentUser['role'] === 'hod') {
    $where[]                = 'campus = :user_campus';
    $params[':user_campus'] = $currentUser['campus'];
}

if (!empty($filters['campus']) && $currentUser['role'] === 'admin') {
    $where[]           = 'campus = :campus';
    $params[':campus'] = $filters['campus'];
}

if (!empty($filters['role'])) {
    $where[]         = 'role = :role';
    $params[':role'] = $filters['role'];
}

if ($filters['status'] !== '') {
    $where[]           = 'is_active = :status';
    $params[':status'] = (int)$filters['status'];
}

$sql = "SELECT id, name, email, campus, role, is_active, created_at
        FROM users
        WHERE " . implode(' AND ', $where) . "
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

$countSql  = "SELECT COUNT(*) FROM users WHERE " . implode(' AND ', $where);
$countStmt = $db->prepare($countSql);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();
$pages = (int)ceil($total / $perPage);

$pageTitle = 'User Management';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container">
    <h1>User Management</h1>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="actions-bar">
        <a href="/user_create.php" class="btn btn-primary">Add New User</a>
    </div>

    <div class="filter-bar">
        <form method="GET" action="./users.php" class="filters-form">
            <?php if ($currentUser['role'] === 'admin'): ?>
            <div class="filter-group">
                <label for="filter_campus">Campus</label>
                <select name="campus" id="filter_campus" class="filter-control">
                    <option value="">All Campuses</option>
                    <?php foreach (CAMPUSES as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $filters['campus'] === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="filter-group">
                <label for="filter_role">Role</label>
                <select name="role" id="filter_role" class="filter-control">
                    <option value="">All Roles</option>
                    <?php foreach (ROLES as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $filters['role'] === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="filter_status">Status</label>
                <select name="status" id="filter_status" class="filter-control">
                    <option value="">All Status</option>
                    <option value="1" <?= $filters['status'] === '1' ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= $filters['status'] === '0' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="./users.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>

    <?php if (empty($users)): ?>
        <div class="empty-state"><p>No users found matching your criteria.</p></div>
    <?php else: ?>
        <table class="list-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Campus</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(CAMPUSES[$user['campus']] ?? $user['campus'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="badge badge-<?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars(ROLES[$user['role']] ?? ucfirst($user['role']), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-<?= $user['is_active'] ? 'completed' : 'cancelled' ?>">
                            <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars(date('d M Y', strtotime($user['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <a href="/user_edit.php?id=<?= (int)$user['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                        <?php if ($user['is_active']): ?>
                            <a href="/user_deactivate.php?id=<?= (int)$user['id'] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Deactivate this user?')">Deactivate</a>
                        <?php else: ?>
                            <a href="/user_activate.php?id=<?= (int)$user['id'] ?>"
                               class="btn btn-sm btn-secondary">Activate</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&<?= http_build_query($filters) ?>">Previous</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>&<?= http_build_query($filters) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $pages): ?>
                <a href="?page=<?= $page + 1 ?>&<?= http_build_query($filters) ?>">Next</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>