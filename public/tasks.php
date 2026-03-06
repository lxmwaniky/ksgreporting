<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

use KSG\Auth;
use KSG\Task;

Auth::startSession();
Auth::requireLogin();

$user       = Auth::user();
$taskObj    = new Task();
$role       = $user['role'];
$campus     = $user['campus'];
$department = $user['department'] ?? null;

// ── Filters 
$filterDept      = $_GET['department']  ?? '';
$filterStaff     = $_GET['staff_id']    ?? '';
$filterDateFrom  = $_GET['date_from']   ?? '';
$filterDateTo    = $_GET['date_to']     ?? '';

// ── Fetch tasks 
if ($role === 'staff') {
    $tasks = $taskObj->getAssignedTo((int)$user['id'], $role, $campus, $department);
    $view  = 'assigned_to_me';
} elseif ($role === 'hod') {
    $view  = $_GET['view'] ?? 'assigned_to_me';
    $tasks = $view === 'assigned_by_me'
        ? $taskObj->getAssignedBy((int)$user['id'], $role, $campus, $department)
        : $taskObj->getAssignedTo((int)$user['id'], $role, $campus, $department);
} else {
    $view  = $_GET['view'] ?? 'assigned_by_me';
    $tasks = $view === 'assigned_to_me'
        ? $taskObj->getAssignedTo((int)$user['id'], $role, $campus, $department)
        : $taskObj->getAssignedBy((int)$user['id'], $role, $campus, $department);
}

// ── Apply filters in PHP
if ($filterDept) {
    $tasks = array_filter($tasks, fn($t) => ($t['department'] ?? '') === $filterDept);
}
if ($filterStaff) {
    $tasks = array_filter($tasks, fn($t) =>
        (string)($t['assigned_to'] ?? '') === $filterStaff ||
        (string)($t['assigned_by'] ?? '') === $filterStaff
    );
}
if ($filterDateFrom) {
    $tasks = array_filter($tasks, fn($t) => $t['deadline'] >= $filterDateFrom);
}
if ($filterDateTo) {
    $tasks = array_filter($tasks, fn($t) => $t['deadline'] <= $filterDateTo);
}
$tasks = array_values($tasks);

// ── Cluster by status 
$clusters = [
    'overdue'     => [],
    'in_progress' => [],
    'pending'     => [],
    'completed'   => [],
];

foreach ($tasks as $task) {
    $s = $task['status'] ?? 'pending';
    if (array_key_exists($s, $clusters)) {
        $clusters[$s][] = $task;
    } else {
        $clusters['pending'][] = $task;
    }
}

$clusterConfig = [
    'overdue'     => ['label' => 'Overdue',     'color' => '#c0392b'],
    'in_progress' => ['label' => 'In Progress',  'color' => '#7f622c'],
    'pending'     => ['label' => 'Pending',      'color' => '#5a4a2a'],
    'completed'   => ['label' => 'Completed',    'color' => '#3a7d44'],
];

// ── Staff list for HoD filter
if ($role === 'hod') {
    $db   = \KSG\Database::getInstance()->getPdo();
    $stmt = $db->prepare("
        SELECT id, name FROM users
        WHERE campus = :campus AND department = :department AND role = 'staff' AND is_active = 1
        ORDER BY name ASC
    ");
    $stmt->execute([':campus' => $campus, ':department' => $department]);
    $staffList = $stmt->fetchAll();
}

$pageTitle = 'Tasks';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h1>Tasks</h1>
        <?php if (in_array($role, ['deputy_director', 'hod', 'admin', 'director'])): ?>
            <a href="task_create.php" class="btn btn-primary">+ Assign Task</a>
        <?php endif; ?>
    </div>
</div>

<?php if (in_array($role, ['deputy_director', 'hod', 'admin', 'director'])): ?>
<div class="filter-bar" style="margin-bottom:1rem;">
    <a href="?view=assigned_by_me<?= $filterDept ? '&department='.urlencode($filterDept) : '' ?><?= $filterStaff ? '&staff_id='.urlencode($filterStaff) : '' ?><?= $filterDateFrom ? '&date_from='.urlencode($filterDateFrom) : '' ?><?= $filterDateTo ? '&date_to='.urlencode($filterDateTo) : '' ?>"
       class="btn <?= $view === 'assigned_by_me' ? 'btn-primary' : 'btn-secondary' ?>">
        Assigned by Me
    </a>
    <a href="?view=assigned_to_me<?= $filterDept ? '&department='.urlencode($filterDept) : '' ?><?= $filterStaff ? '&staff_id='.urlencode($filterStaff) : '' ?><?= $filterDateFrom ? '&date_from='.urlencode($filterDateFrom) : '' ?><?= $filterDateTo ? '&date_to='.urlencode($filterDateTo) : '' ?>"
       class="btn <?= $view === 'assigned_to_me' ? 'btn-primary' : 'btn-secondary' ?>">
        Assigned to Me
    </a>
</div>
<?php endif; ?>

<?php
// Show filters for: director (department + date), deputy_director (department + date),
//                   hod (staff + date), staff (date only)
$showFilters = true;
?>
<?php if ($showFilters): ?>
<div class="filter-bar">
    <form method="GET" action="tasks.php" class="filters-form">
        <input type="hidden" name="view" value="<?= htmlspecialchars($view, ENT_QUOTES, 'UTF-8') ?>">

        <?php if (in_array($role, ['director', 'deputy_director', 'admin'])): ?>
        <div class="filter-group">
            <label>Department</label>
            <select name="department" class="filter-control">
                <option value="">All Departments</option>
                <?php foreach (DEPARTMENTS as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $filterDept === $key ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if ($role === 'hod' && !empty($staffList)): ?>
        <div class="filter-group">
            <label>Staff Member</label>
            <select name="staff_id" class="filter-control">
                <option value="">All Staff</option>
                <?php foreach ($staffList as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= $filterStaff == $s['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="filter-group">
            <label>Deadline From</label>
            <input type="date" name="date_from" class="filter-control"
                   value="<?= htmlspecialchars($filterDateFrom, ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="filter-group">
            <label>Deadline To</label>
            <input type="date" name="date_to" class="filter-control"
                   value="<?= htmlspecialchars($filterDateTo, ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="tasks.php?view=<?= htmlspecialchars($view, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary">Clear</a>
    </form>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (empty($tasks)): ?>
    <div class="card">
        <p class="empty-state">No tasks found.</p>
    </div>
<?php else: ?>

<?php foreach ($clusterConfig as $status => $config): ?>
<?php
    $statusTasks = $clusters[$status];
    $count       = count($statusTasks);
    $color       = $config['color'];
    $label       = $config['label'];
?>
<div class="task-cluster">
    <div class="task-cluster-header" onclick="toggleCluster('<?= $status ?>')" style="cursor:pointer;">
        <span class="task-cluster-title" style="color:<?= $color ?>;">
            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            <span class="task-cluster-count"><?= $count ?></span>
        </span>
        <span class="task-cluster-toggle" id="toggle-<?= $status ?>">Show ▾</span>
    </div>

    <div id="body-<?= $status ?>" style="display:none;">
        <?php if ($count === 0): ?>
            <p class="empty-state-inline" style="padding:.5rem 0 1rem; padding-left:.25rem;">
                No <?= strtolower($label) ?> tasks.
            </p>
        <?php else: ?>
        <div class="card" style="margin-top:.5rem;">
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
                    <?php foreach ($statusTasks as $i => $task): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?= htmlspecialchars(DEPARTMENTS[$task['department']] ?? $task['department'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($task['section'])): ?>
                                <br><small><?= htmlspecialchars(SECTIONS[$task['department']][$task['section']] ?? $task['section'], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $view === 'assigned_by_me'
                                ? htmlspecialchars($task['assigned_to_name'] ?? '—', ENT_QUOTES, 'UTF-8')
                                : htmlspecialchars($task['assigned_by_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>
                            <?php $isOverdue = $task['status'] !== 'completed' && strtotime($task['deadline']) < time(); ?>
                            <span <?= $isOverdue ? 'style="color:var(--danger);font-weight:600;"' : '' ?>>
                                <?= htmlspecialchars(date(DISPLAY_DATE_FORMAT, strtotime($task['deadline'])), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
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
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<style>
.task-cluster { margin-bottom: 1rem; }
.task-cluster-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .6rem .75rem;
    border-radius: var(--radius);
    background: #f5f0e8;
    border-left: 4px solid currentColor;
    user-select: none;
}
.task-cluster-header:hover { background: #ede8de; }
.task-cluster-title {
    font-size: .95rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: .5rem;
}
.task-cluster-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,.08);
    border-radius: 20px;
    font-size: .75rem;
    font-weight: 700;
    min-width: 22px;
    height: 22px;
    padding: 0 .45rem;
}
.task-cluster-toggle { font-size: .82rem; color: #888; font-weight: 600; }
</style>

<script>
function toggleCluster(status) {
    const body   = document.getElementById('body-' + status);
    const toggle = document.getElementById('toggle-' + status);
    if (!body) return;
    const hidden = body.style.display === 'none';
    body.style.display   = hidden ? 'block' : 'none';
    toggle.textContent   = hidden ? 'Hide ▴' : 'Show ▾';
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>