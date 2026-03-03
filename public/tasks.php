<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

use KSG\Auth;
use KSG\Task;

Auth::startSession();
Auth::requireLogin();

$user    = Auth::user();
$taskObj = new Task();
$role    = $user['role'];

if ($role === 'staff') {
    $tasks = $taskObj->getAssignedTo((int)$user['id']);
    $view  = 'assigned_to_me';
} elseif ($role === 'hod') {
    $view  = $_GET['view'] ?? 'assigned_to_me';
    $tasks = $view === 'assigned_by_me'
        ? $taskObj->getAssignedBy((int)$user['id'])
        : $taskObj->getAssignedTo((int)$user['id']);
} else {
    $view  = $_GET['view'] ?? 'assigned_by_me';
    $tasks = $view === 'assigned_to_me'
        ? $taskObj->getAssignedTo((int)$user['id'])
        : $taskObj->getAssignedBy((int)$user['id']);
}

$pageTitle = 'Tasks';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h1>Tasks</h1>
        <?php if (in_array($role, ['deputy_director', 'hod', 'admin'])): ?>
            <a href="task_create.php" class="btn btn-primary">+ Assign Task</a>
        <?php endif; ?>
    </div>
</div>

<?php if (in_array($role, ['deputy_director', 'hod', 'admin', 'director'])): ?>
<div class="filter-bar">
    <a href="?view=assigned_by_me" class="btn <?= $view === 'assigned_by_me' ? 'btn-primary' : 'btn-secondary' ?>">Assigned by Me</a>
    <a href="?view=assigned_to_me" class="btn <?= $view === 'assigned_to_me' ? 'btn-primary' : 'btn-secondary' ?>">Assigned to Me</a>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<div class="card">
    <?php if (empty($tasks)): ?>
        <p class="empty-state">No tasks found.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Department</th>
                    <th><?= $view === 'assigned_by_me' ? 'Assigned To' : 'Assigned By' ?></th>
                    <th>Deadline</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $i => $task): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?= htmlspecialchars(DEPARTMENTS[$task['department']] ?? $task['department'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($task['section'])): ?>
                            <br><small><?= htmlspecialchars(SECTIONS[$task['department']][$task['section']] ?? $task['section'], ENT_QUOTES, 'UTF-8') ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $view === 'assigned_by_me'
                            ? htmlspecialchars($task['assigned_to_name'] ?? '—', ENT_QUOTES, 'UTF-8')
                            : htmlspecialchars($task['assigned_by_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td><?= htmlspecialchars(date(DISPLAY_DATE_FORMAT, strtotime($task['deadline'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="badge badge-<?= htmlspecialchars($task['status'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars(TASK_STATUSES[$task['status']] ?? $task['status'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td>
                        <a href="task_view.php?id=<?= (int)$task['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>