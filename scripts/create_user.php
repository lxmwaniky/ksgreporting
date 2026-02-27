<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use KSG\Auth;
use KSG\Database;

Auth::startSession();
Auth::requireLogin();

$currentUser = Auth::user();

if (!in_array($currentUser['role'], ['admin', 'director'])) {
    $_SESSION['error'] = 'Access denied';
    header('Location: /index.php');
    exit;
}

$error = $_SESSION['error'] ?? null;
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['error'], $_SESSION['form_data']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        die('Invalid security token');
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $campus = $_POST['campus'] ?? '';
    $department = $_POST['department'] ?? '';
    $role = $_POST['role'] ?? '';

    $errors = [];

    if (empty($name)) {
        $errors[] = 'Name is required';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email address is required';
    }

    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must be at least 8 characters with one uppercase letter and one number';
    }

    if (empty($campus) || !isset(CAMPUSES[$campus])) {
        $errors[] = 'Valid campus selection is required';
    }

    if ($currentUser['role'] === 'director' && $campus !== $currentUser['campus']) {
        $errors[] = 'You can only create users for your own campus';
    }

    $allowedRoles = ['staff', 'hod', 'director'];
    if ($currentUser['role'] === 'admin') {
        $allowedRoles[] = 'admin';
    }

    if (empty($role) || !in_array($role, $allowedRoles)) {
        $errors[] = 'Valid role selection is required';
    }

    if ($role === 'hod' && (empty($department) || !isset(DEPARTMENTS[$department]))) {
        $errors[] = 'Department is required for HoD role';
    }

    if (empty($errors)) {
        try {
            $db = Database::getInstance()->getPdo();

            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                throw new \Exception('Email address already exists');
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $db->prepare("
                INSERT INTO users (name, email, password_hash, campus, department, role, is_active, created_at)
                VALUES (:name, :email, :password_hash, :campus, :department, :role, 1, CURRENT_TIMESTAMP)
            ");

            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password_hash' => $passwordHash,
                ':campus' => $campus,
                ':department' => $role === 'hod' ? $department : null,
                ':role' => $role,
            ]);

            $_SESSION['success'] = "User {$name} created successfully";
            header('Location: /users.php');
            exit;

        } catch (\Exception $e) {
            $error = $e->getMessage();
            $formData = $_POST;
        }
    } else {
        $error = implode(', ', $errors);
        $formData = $_POST;
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="container">
    <h1>Add New User</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="/user_create.php">
            <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">

            <div class="form-group">
                <label for="name">Full Name *</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($formData['name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($formData['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required>
                <small>Minimum 8 characters, one uppercase letter, one number</small>
            </div>

            <div class="form-group">
                <label for="campus">Campus *</label>
                <select id="campus" name="campus" required>
                    <option value="">Select Campus</option>
                    <?php foreach (CAMPUSES as $key => $label): ?>
                        <?php if ($currentUser['role'] === 'admin' || $key === $currentUser['campus']): ?>
                            <option value="<?= $key ?>" <?= ($formData['campus'] ?? '') === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="role">Role *</label>
                <select id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="staff" <?= ($formData['role'] ?? '') === 'staff' ? 'selected' : '' ?>>Staff</option>
                    <option value="hod" <?= ($formData['role'] ?? '') === 'hod' ? 'selected' : '' ?>>Head of Department</option>
                    <option value="director" <?= ($formData['role'] ?? '') === 'director' ? 'selected' : '' ?>>Director</option>
                    <?php if ($currentUser['role'] === 'admin'): ?>
                        <option value="admin" <?= ($formData['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group" id="department_field" style="display: none;">
                <label for="department">Department *</label>
                <select id="department" name="department">
                    <option value="">Select Department</option>
                    <?php foreach (DEPARTMENTS as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($formData['department'] ?? '') === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create User</button>
                <a href="/users.php" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('role').addEventListener('change', function() {
    const departmentField = document.getElementById('department_field');
    const departmentSelect = document.getElementById('department');
    
    if (this.value === 'hod') {
        departmentField.style.display = 'block';
        departmentSelect.required = true;
    } else {
        departmentField.style.display = 'none';
        departmentSelect.required = false;
    }
});

if (document.getElementById('role').value === 'hod') {
    document.getElementById('department_field').style.display = 'block';
    document.getElementById('department').required = true;
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>