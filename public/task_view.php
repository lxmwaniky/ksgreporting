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
$id      = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: tasks.php');
    exit;
}

$task = $taskObj->getById($id);

if (!$task) {
    header('Location: tasks.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $allowed = ['pending', 'in_progress', 'completed', 'overdue'];
    $status  = $_POST['status'];
    if (in_array($status, $allowed) && (int)$task['assigned_to'] === (int)$user['id']) {
        $taskObj->updateStatus($id, $status);
        header('Location: task_view.php?id=' . $id);
        exit;
    }
}

$pageTitle = 'Task Details';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h1>Task Details</h1>
        <a href="tasks.php" class="btn btn-secondary">Back to Tasks</a>
    </div>
</div>

<div class="card">
    <table class="detail-table">
        <tr>
            <th>Title</th>
            <td><?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Description</th>
            <td><?= nl2br(htmlspecialchars($task['description'], ENT_QUOTES, 'UTF-8')) ?></td>
        </tr>
        <tr>
            <th>Department</th>
            <td>
                <?= htmlspecialchars(DEPARTMENTS[$task['department']] ?? $task['department'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                <?php if (!empty($task['section'])): ?>
                    &mdash; <?= htmlspecialchars(SECTIONS[$task['department']][$task['section']] ?? $task['section'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th>Assigned By</th>
            <td><?= htmlspecialchars($task['assigned_by_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Assigned To</th>
            <td><?= htmlspecialchars($task['assigned_to_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Deadline</th>
            <td><?= htmlspecialchars(date(DISPLAY_DATE_FORMAT, strtotime($task['deadline'])), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td>
                <span class="badge badge-<?= htmlspecialchars($task['status'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars(TASK_STATUSES[$task['status']] ?? $task['status'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            </td>
        </tr>
        <tr>
            <th>Created</th>
            <td><?= htmlspecialchars(date(DISPLAY_DATE_FORMAT, strtotime($task['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
    </table>

    <?php if ((int)$task['assigned_to'] === (int)$user['id'] && $task['status'] !== 'completed'): ?>
    <div style="margin-top:1.5rem;border-top:1px solid var(--border);padding-top:1.5rem;">
        <h3 style="margin-bottom:1rem;">Update Status</h3>
        <form method="POST" action="task_view.php?id=<?= $id ?>">
            <div class="form-row">
                <div class="form-group">
                    <select name="status">
                        <?php foreach (TASK_STATUSES as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $task['status'] === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>