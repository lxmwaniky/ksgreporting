<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use KSG\Auth;
use KSG\Database;

Auth::startSession();
Auth::requireLogin();

if (Auth::user()['role'] !== 'admin') {
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

    $campus = trim($_POST['campus'] ?? '');
    $directorName = trim($_POST['director_name'] ?? '');
    $directorEmail = trim($_POST['director_email'] ?? '');
    $createUser = isset($_POST['create_user']) && $_POST['create_user'] === '1';
    $password = $_POST['password'] ?? '';

    $errors = [];

    if (empty($campus) || !isset(CAMPUSES[$campus])) {
        $errors[] = 'Valid campus selection required';
    }

    if (empty($directorName)) {
        $errors[] = 'Director name required';
    }

    if (empty($directorEmail) || !filter_var($directorEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email address required';
    }

    if ($createUser) {
        if (empty($password)) {
            $errors[] = 'Password required when creating user account';
        } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must be at least 8 characters with one uppercase letter and one number';
        }
    }

    if (empty($errors)) {
        try {
            $db = Database::getInstance()->getPdo();
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT id FROM campus_directors WHERE campus = :campus");
            $stmt->execute([':campus' => $campus]);
            if ($stmt->fetch()) {
                throw new Exception('A director already exists for this campus');
            }

            $stmt = $db->prepare("SELECT id FROM campus_directors WHERE director_email = :email");
            $stmt->execute([':email' => $directorEmail]);
            if ($stmt->fetch()) {
                throw new Exception('This email is already assigned to another director');
            }

            $userId = null;
            if ($createUser) {
                $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
                $stmt->execute([':email' => $directorEmail]);
                if ($stmt->fetch()) {
                    throw new Exception('Email already exists in user accounts');
                }

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    INSERT INTO users (name, email, password_hash, campus, role, is_active, created_at)
                    VALUES (:name, :email, :password_hash, :campus, 'director', 1, CURRENT_TIMESTAMP)
                    RETURNING id
                ");
                $stmt->execute([
                    ':name' => $directorName,
                    ':email' => $directorEmail,
                    ':password_hash' => $passwordHash,
                    ':campus' => $campus,
                ]);
                $result = $stmt->fetch();
                $userId = $result['id'];
            }

            $stmt = $db->prepare("
                INSERT INTO campus_directors (campus, director_name, director_email, user_id, is_active, created_at)
                VALUES (:campus, :name, :email, :user_id, 1, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                ':campus' => $campus,
                ':name' => $directorName,
                ':email' => $directorEmail,
                ':user_id' => $userId,
            ]);

            $db->commit();

            $_SESSION['success'] = 'Director added successfully';
            header('Location: /directors.php');
            exit;

        } catch (Exception $e) {
            $db->rollBack();
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
    <h1>Add Campus Director</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="./director_create.php">
            <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">

            <div class="form-group">
                <label for="campus">Campus *</label>
                <select name="campus" id="campus" required>
                    <option value="">Select Campus</option>
                    <?php foreach (CAMPUSES as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($formData['campus'] ?? '') === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="director_name">Director Name *</label>
                <input type="text" id="director_name" name="director_name" 
                       value="<?= htmlspecialchars($formData['director_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="director_email">Director Email *</label>
                <input type="email" id="director_email" name="director_email" 
                       value="<?= htmlspecialchars($formData['director_email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="create_user" value="1" id="create_user_checkbox"
                           <?= isset($formData['create_user']) && $formData['create_user'] === '1' ? 'checked' : '' ?>>
                    Create user account for this director
                </label>
            </div>

            <div class="form-group" id="password_field" style="display: none;">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password">
                <small>Minimum 8 characters, one uppercase letter, one number</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Add Director</button>
                <a href="./directors.php" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('create_user_checkbox').addEventListener('change', function() {
    document.getElementById('password_field').style.display = this.checked ? 'block' : 'none';
});

if (document.getElementById('create_user_checkbox').checked) {
    document.getElementById('password_field').style.display = 'block';
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>