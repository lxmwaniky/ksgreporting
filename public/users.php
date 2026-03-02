<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use KSG\Auth;
use KSG\Database;

Auth::startSession();
Auth::requireLogin();

$currentUser = Auth::currentUser();
if (!in_array($currentUser['role'], ['admin', 'hod'])) {
    http_response_code(403);
    die('Access denied. Admin or HoD privileges required.');
}

$db = Database::getInstance()->getPdo();

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$filters = [
    'campus' => $_GET['campus'] ?? '',
    'role' => $_GET['role'] ?? '',
    'status' => $_GET['status'] ?? '',
];

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];

if ($currentUser['role'] === 'hod') {
    $where[] = 'campus = :user_campus';
    $params[':user_campus'] = $currentUser['campus'];
}

if (!empty($filters['campus'])) {
    $where[] = 'campus = :campus';
    $params[':campus'] = $filters['campus'];
}

if (!empty($filters['role'])) {
    $where[] = 'role = :role';
    $params[':role'] = $filters['role'];
}

if (!empty($filters['status'])) {
    $where[] = 'is_active = :status';
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
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

$countSql = "SELECT COUNT(*) FROM users WHERE " . implode(' AND ', $where);
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
        <div class="alert alert-success">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
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
                    <option value="staff" <?= $filters['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                    <option value="hod" <?= $filters['role'] === 'hod' ? 'selected' : '' ?>>HoD</option>
                    <?php if ($currentUser['role'] === 'admin'): ?>
                    <option value="admin" <?= $filters['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <?php endif; ?>
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
        <div class="empty-state">
            <p>No users found matching your criteria.</p>
        </div>
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
                        <td><span class="badge badge-<?= $user['role'] ?>"><?= htmlspecialchars(ucfirst($user['role']), ENT_QUOTES, 'UTF-8') ?></span></td>
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
                    <a href="?page=<?= $page - 1 ?><?= http_build_query($filters) ? '&' . http_build_query($filters) : '' ?>">Previous</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?><?= http_build_query($filters) ? '&' . http_build_query($filters) : '' ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= http_build_query($filters) ? '&' . http_build_query($filters) : '' ?>">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>