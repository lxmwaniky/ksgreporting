<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use KSG\Auth;
use KSG\Database;

Auth::startSession();
Auth::requireLogin();

$currentUser = Auth::currentUser();
if (!in_array($currentUser['role'], ['admin', 'director'])) {
    http_response_code(403);
    die('Access denied.');
}

$db    = Database::getInstance()->getPdo();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid security token.');
    }

    $name        = trim($_POST['name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $campus      = $_POST['campus'] ?? '';
    $role        = $_POST['role'] ?? '';
    $department  = $_POST['department'] ?? '';
    $hodName     = trim($_POST['hod_name'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $allowedRoles = $currentUser['role'] === 'admin'
        ? ['staff', 'hod', 'director', 'admin']
        : ['staff', 'hod'];

    if (empty($name) || empty($email) || empty($campus) || empty($role) || empty($password)) {
        $error = 'Name, email, campus, role and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!array_key_exists($campus, CAMPUSES)) {
        $error = 'Invalid campus selected.';
    } elseif (!in_array($role, $allowedRoles)) {
        $error = 'Invalid role selected.';
    } elseif ($currentUser['role'] === 'director' && $campus !== $currentUser['campus']) {
        $error = 'Directors can only create users for their own campus.';
    } else {
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);

            if ($stmt->fetch()) {
                $error = 'A user with this email already exists.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $db->prepare("
                    INSERT INTO users
                        (name, email, password_hash, campus, role, department, hod_name, designation, is_active, created_at)
                    VALUES
                        (:name, :email, :password_hash, :campus, :role, :department, :hod_name, :designation, 1, CURRENT_TIMESTAMP)
                ");

                $stmt->execute([
                    ':name'          => $name,
                    ':email'         => $email,
                    ':password_hash' => $passwordHash,
                    ':campus'        => $campus,
                    ':role'          => $role,
                    ':department'    => $department ?: null,
                    ':hod_name'      => $hodName ?: null,
                    ':designation'   => $designation ?: null,
                ]);

                $_SESSION['success'] = 'User created successfully.';
                header('Location: /users.php');
                exit;
            }
        } catch (\PDOException $e) {
            error_log('User creation error: ' . $e->getMessage());
            $error = 'A database error occurred. Please try again.';
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Add New User</h1>
        <a href="./users.php" class="btn btn-outline">&#8592; Back to Users</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="./user_create.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

        <fieldset>
            <legend>Account Details</legend>
            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Full Name <span class="required">*</span></label>
                    <input type="text" name="name" id="name"
                           value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           required maxlength="150">
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" name="email" id="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           required maxlength="200">
                </div>

                <div class="form-group">
                    <label for="designation">Designation / Job Title</label>
                    <input type="text" name="designation" id="designation"
                           value="<?= htmlspecialchars($_POST['designation'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="e.g. ICT Officer" maxlength="150">
                </div>

                <div class="form-group">
                    <label for="campus">Campus <span class="required">*</span></label>
                    <select name="campus" id="campus" required>
                        <option value="">-- Select Campus --</option>
                        <?php foreach (CAMPUSES as $key => $label): ?>
                            <?php if ($currentUser['role'] === 'admin' || $key === $currentUser['campus']): ?>
                                <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= ($_POST['campus'] ?? '') === $key ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="department">Department</label>
                    <select name="department" id="department">
                        <option value="">-- Select Department --</option>
                        <?php foreach (DEPARTMENTS as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                <?= ($_POST['department'] ?? '') === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="hod_name">Head of Department Name</label>
                    <input type="text" name="hod_name" id="hod_name"
                           value="<?= htmlspecialchars($_POST['hod_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="e.g. Eng. Maurice Odida" maxlength="150">
                    <small>The HoD for this user's department</small>
                </div>

                <div class="form-group">
                    <label for="role">Role <span class="required">*</span></label>
                    <select name="role" id="role" required>
                        <option value="">-- Select Role --</option>
                        <option value="staff" <?= ($_POST['role'] ?? '') === 'staff' ? 'selected' : '' ?>>Staff</option>
                        <option value="hod" <?= ($_POST['role'] ?? '') === 'hod' ? 'selected' : '' ?>>HoD</option>
                        <?php if ($currentUser['role'] === 'admin'): ?>
                            <option value="director" <?= ($_POST['role'] ?? '') === 'director' ? 'selected' : '' ?>>Director</option>
                            <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Password</legend>
            <div class="form-grid">
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" name="password" id="password" required>
                    <small>Min. 8 characters, one uppercase letter, one number</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>
            </div>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create User</button>
            <a href="./users.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>