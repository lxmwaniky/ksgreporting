<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use KSG\Auth;
use KSG\Database;

Auth::startSession();
Auth::requireLogin();

if (Auth::user()['role'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Only administrators can manage directors.';
    header('Location: /index.php');
    exit;
}

$db = Database::getInstance()->getPdo();
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$stmt = $db->query("
    SELECT cd.*, u.name as user_name, u.email as user_email, u.is_active as user_status
    FROM campus_directors cd
    LEFT JOIN users u ON cd.user_id = u.id
    ORDER BY cd.campus
");
$directors = $stmt->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Campus Directors Management</h1>
        <a href="/director_create.php" class="btn btn-primary">Add New Director</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>Campus</th>
                    <th>Director Name</th>
                    <th>Email</th>
                    <th>User Account</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($directors)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px;">
                            No directors found. Add directors to start managing campus leadership.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($directors as $director): ?>
                        <tr>
                            <td><?= htmlspecialchars(CAMPUSES[$director['campus']] ?? $director['campus']) ?></td>
                            <td><?= htmlspecialchars($director['director_name']) ?></td>
                            <td><?= htmlspecialchars($director['director_email']) ?></td>
                            <td>
                                <?php if ($director['user_id']): ?>
                                    <span class="badge badge-info">
                                        <?= htmlspecialchars($director['user_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-warning">No Account</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($director['is_active']): ?>
                                    <span class="status-badge status-completed">Active</span>
                                <?php else: ?>
                                    <span class="status-badge status-delayed">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="/director_edit.php?id=<?= $director['id'] ?>" class="btn btn-small">Edit</a>
                                <?php if ($director['is_active']): ?>
                                    <a href="/director_deactivate.php?id=<?= $director['id'] ?>" 
                                       class="btn btn-small btn-danger"
                                       onclick="return confirm('Deactivate this director?')">Deactivate</a>
                                <?php else: ?>
                                    <a href="/director_activate.php?id=<?= $director['id'] ?>" 
                                       class="btn btn-small btn-success">Activate</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>