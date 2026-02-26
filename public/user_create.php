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
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid security token.');
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $campus = $_POST['campus'] ?? '';
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($campus) || empty($role) || empty($password)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number';
    } elseif (!array_key_exists($campus, CAMPUSES)) {
        $error = 'Invalid campus selected';
    } elseif (!in_array($role, ['staff', 'hod', 'admin'])) {
        $error = 'Invalid role selected';
    } elseif ($currentUser['role'] === 'hod' && $campus !== $currentUser['campus']) {
        $error = 'HoD can only create users for their own campus';
    } elseif ($currentUser['role'] === 'hod' && $role === 'admin') {
        $error = 'HoD cannot create admin users';
    } else {
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            
            if ($stmt->fetch()) {
                $error = 'A user with this email already exists';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("
                    INSERT INTO users (name, email, password_hash, campus, role, is_active, created_at)
                    VALUES (:name, :email, :password_hash, :campus, :role, 1, CURRENT_TIMESTAMP)
                ");
                
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':password_hash' => $passwordHash,
                    ':campus' => $campus,
                    ':role' => $role,
                ]);
                
                $_SESSION['success'] = 'User created successfully';
                header('Location: /users.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('User creation error: ' . $e->getMessage());
            $error = 'Database error occurred. Please try again.';
        }
    }
}

$pageTitle = 'Add New User';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container">
    <h1>Add New User</h1>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/user_create.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

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
                <label for="campus">Campus <span class="required">*</span></label>
                <select name="campus" id="campus" required>
                    <option value="">Select Campus</option>
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
                <label for="role">Role <span class="required">*</span></label>
                <select name="role" id="role" required>
                    <option value="">Select Role</option>
                    <option value="staff" <?= ($_POST['role'] ?? '') === 'staff' ? 'selected' : '' ?>>Staff</option>
                    <option value="hod" <?= ($_POST['role'] ?? '') === 'hod' ? 'selected' : '' ?>>HoD</option>
                    <?php if ($currentUser['role'] === 'admin'): ?>
                        <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="password">Password <span class="required">*</span></label>
                <input type="password" name="password" id="password" required>
                <small>Minimum 8 characters, one uppercase, one number</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                <input type="password" name="confirm_password" id="confirm_password" required>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create User</button>
            <a href="/users.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
