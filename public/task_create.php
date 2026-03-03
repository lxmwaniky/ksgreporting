<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

use KSG\Auth;
use KSG\Task;
use KSG\Mailer;

Auth::startSession();
Auth::requireLogin();

$user = Auth::user();

if (!in_array($user['role'], ['deputy_director', 'hod', 'admin'])) {
    header('Location: tasks.php');
    exit;
}

$taskObj = new Task();
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title'       => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'assigned_to' => (int)($_POST['assigned_to'] ?? 0),
        'assigned_by' => (int)$user['id'],
        'department'  => $_POST['department'] ?? '',
        'section'     => !empty($_POST['section']) ? $_POST['section'] : null,
        'campus'      => $user['campus'],
        'deadline'    => $_POST['deadline'] ?? '',
    ];

    if (empty($data['title']))       $errors[] = 'Task title is required.';
    if (empty($data['description'])) $errors[] = 'Task description is required.';
    if (empty($data['assigned_to'])) $errors[] = 'Please select who to assign this task to.';
    if (empty($data['department']))  $errors[] = 'Please select a department.';
    if (empty($data['deadline']))    $errors[] = 'Deadline is required.';

    if (empty($errors)) {
        try {
            $taskId = $taskObj->create($data);
            $mailer = new Mailer();
            $mailer->sendTaskNotification($taskId, $data);
            $_SESSION['success'] = 'Task assigned successfully.';
            header('Location: tasks.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Failed to create task. Please try again.';
        }
    }
}

$assignees = $user['role'] === 'deputy_director'
    ? $taskObj->getHodsInCampus($user['campus'])
    : $taskObj->getStaffInDepartment($user['campus'], $user['department']);

$pageTitle = 'Assign Task';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h1>Assign Task</h1>
        <a href="tasks.php" class="btn btn-secondary">Back to Tasks</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <strong>Please fix the following:</strong>
        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="task_create.php">

        <div class="form-group">
            <label for="title">Task Title <span class="required">*</span></label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="255" required>
        </div>

        <div class="form-group">
            <label for="description">Task Description <span class="required">*</span></label>
            <textarea id="description" name="description" rows="4" maxlength="2000" required><?= htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="department">Department <span class="required">*</span></label>
                <select id="department" name="department" required>
                    <option value="">-- Select Department --</option>
                    <?php foreach (DEPARTMENTS as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= (($_POST['department'] ?? '') === $key) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="sectionGroup" style="display:none;">
                <label for="section">Section</label>
                <select id="section" name="section">
                    <option value="">-- Select Section --</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="assigned_to">Assign To <span class="required">*</span></label>
            <select id="assigned_to" name="assigned_to" required>
                <option value="">-- Select Person --</option>
                <?php foreach ($assignees as $person): ?>
                    <option value="<?= (int)$person['id'] ?>" <?= (($_POST['assigned_to'] ?? '') == $person['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($person['name'], ENT_QUOTES, 'UTF-8') ?>
                        (<?= htmlspecialchars(DEPARTMENTS[$person['department']] ?? $person['department'], ENT_QUOTES, 'UTF-8') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="deadline">Deadline <span class="required">*</span></label>
            <input type="date" id="deadline" name="deadline" value="<?= htmlspecialchars($_POST['deadline'] ?? '', ENT_QUOTES, 'UTF-8') ?>" min="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Assign Task</button>
            <a href="tasks.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
const sections = <?= json_encode(SECTIONS) ?>;
document.getElementById('department').addEventListener('change', function () {
    const dept = this.value;
    const grp  = document.getElementById('sectionGroup');
    const sel  = document.getElementById('section');
    sel.innerHTML = '<option value="">-- Select Section --</option>';
    if (dept && sections[dept] && Object.keys(sections[dept]).length > 0) {
        grp.style.display = 'block';
        Object.entries(sections[dept]).forEach(([k, v]) => {
            const o = document.createElement('option');
            o.value = k; o.textContent = v;
            sel.appendChild(o);
        });
    } else {
        grp.style.display = 'none';
    }
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>