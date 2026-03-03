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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid director ID';
    header('Location: /directors.php');
    exit;
}

$directorId = (int) $_GET['id'];
$db = Database::getInstance()->getPdo();

$error   = null;
$success = null;
$formData = [];

$stmt = $db->prepare("SELECT * FROM campus_directors WHERE id = :id");
$stmt->execute([':id' => $directorId]);
$director = $stmt->fetch();

if (!$director) {
    $_SESSION['error'] = 'Director not found';
    header('Location: /directors.php');
    exit;
}

// Fetch linked user account if any
$stmt = $db->prepare("SELECT id, name, email, is_active FROM users WHERE email = :email AND role = 'director'");
$stmt->execute([':email' => $director['director_email']]);
$linkedUser = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        die('Invalid security token');
    }

    $directorName  = trim($_POST['director_name'] ?? '');
    $directorEmail = trim($_POST['director_email'] ?? '');
    $newPassword   = trim($_POST['new_password'] ?? '');
    $createAccount = isset($_POST['create_account']);

    if (empty($directorName) || empty($directorEmail)) {
        $error = 'Director name and email are required.';
    } elseif (!filter_var($directorEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'A valid email address is required.';
    } elseif (!empty($newPassword) && strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        try {
            $db->beginTransaction();

            
            $stmt = $db->prepare("
                UPDATE campus_directors
                SET director_name = :name, director_email = :email, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([':name' => $directorName, ':email' => $directorEmail, ':id' => $directorId]);

            
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email AND role = 'director'");
            $stmt->execute([':email' => $director['director_email']]);
            $existingUser = $stmt->fetch();

            if ($existingUser) {
                $updateFields = "name = :name, email = :email, updated_at = CURRENT_TIMESTAMP";
                $params = [':name' => $directorName, ':email' => $directorEmail, ':id' => $existingUser['id']];

                if (!empty($newPassword)) {
                    $updateFields .= ", password_hash = :hash";
                    $params[':hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                }

                $stmt = $db->prepare("UPDATE users SET $updateFields WHERE id = :id");
                $stmt->execute($params);
                $accountMsg = 'User account updated.';

            } elseif ($createAccount || !empty($newPassword)) {
                $password = !empty($newPassword) ? $newPassword : bin2hex(random_bytes(8));
                $stmt = $db->prepare("
                    INSERT INTO users (name, email, password_hash, campus, role, is_active, created_at, updated_at)
                    VALUES (:name, :email, :hash, :campus, 'director', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([
                    ':name'   => $directorName,
                    ':email'  => $directorEmail,
                    ':hash'   => password_hash($password, PASSWORD_DEFAULT),
                    ':campus' => $director['campus'],
                ]);
                $accountMsg = !empty($newPassword)
                    ? 'User account created with the password you set.'
                    : "User account created. Temporary password: $password";
            }

            $db->commit();
            $_SESSION['success'] = 'Director updated successfully. ' . ($accountMsg ?? '');
            header('Location: /directors.php');
            exit;

        } catch (PDOException $e) {
            $db->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    }

    $formData = $_POST;
} else {
    $formData = $director;
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="container">
    <div class="page-header">
        <div class="page-header-content">
            <h1>Edit Campus Director</h1>
            <a href="./directors.php" class="btn btn-secondary">Back to Directors</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">

            <div class="form-group">
                <label>Campus</label>
                <input type="text" value="<?= htmlspecialchars(CAMPUSES[$director['campus']] ?? $director['campus'], ENT_QUOTES, 'UTF-8') ?>" disabled>
            </div>

            <div class="form-group">
                <label for="director_name">Director Name <span class="required">*</span></label>
                <input type="text" id="director_name" name="director_name"
                       value="<?= htmlspecialchars($formData['director_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <div class="form-group">
                <label for="director_email">Director Email <span class="required">*</span></label>
                <input type="email" id="director_email" name="director_email"
                       value="<?= htmlspecialchars($formData['director_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <!-- User Account Section -->
            <div class="card" style="background:var(--bg-alt,#f9f9f9);margin-top:1.5rem;padding:1.25rem;">
                <h3 style="margin-bottom:1rem;">
                    User Account
                    <?php if ($linkedUser): ?>
                        <span class="badge badge-completed" style="font-size:0.75rem;margin-left:0.5rem;">Active Account</span>
                    <?php else: ?>
                        <span class="badge badge-pending" style="font-size:0.75rem;margin-left:0.5rem;">No Account</span>
                    <?php endif; ?>
                </h3>

                <?php if (!$linkedUser): ?>
                <div class="form-group" style="display:flex;align-items:center;gap:0.75rem;">
                    <input type="checkbox" id="create_account" name="create_account" value="1"
                           <?= isset($_POST['create_account']) ? 'checked' : '' ?>>
                    <label for="create_account" style="margin:0;">
                        Create a login account for this director
                        <small style="display:block;color:#666;">A temporary password will be generated if you leave the password field blank.</small>
                    </label>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="new_password">
                        <?= $linkedUser ? 'Change Password' : 'Set Password' ?>
                        <?= $linkedUser ? '' : '<small>(optional — leave blank to auto-generate)</small>' ?>
                    </label>
                    <input type="password" id="new_password" name="new_password"
                           placeholder="Min 8 characters" autocomplete="new-password">
                </div>
            </div>

            <div class="form-actions" style="margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary">Update Director</button>
                <a href="./directors.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>