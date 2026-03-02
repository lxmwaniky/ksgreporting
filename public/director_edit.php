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

$error = null;
$formData = [];

$stmt = $db->prepare("SELECT * FROM campus_directors WHERE id = :id");
$stmt->execute([':id' => $directorId]);
$director = $stmt->fetch();

if (!$director) {
    $_SESSION['error'] = 'Director not found';
    header('Location: /directors.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        die('Invalid security token');
    }

    $directorName = trim($_POST['director_name'] ?? '');
    $directorEmail = trim($_POST['director_email'] ?? '');

    if (empty($directorName) || empty($directorEmail)) {
        $error = 'All fields required';
    } elseif (!filter_var($directorEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Valid email address required';
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE campus_directors 
                SET director_name = :name, director_email = :email, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                ':name' => $directorName,
                ':email' => $directorEmail,
                ':id' => $directorId,
            ]);

            $_SESSION['success'] = 'Director updated successfully';
            header('Location: /directors.php');
            exit;

        } catch (PDOException $e) {
            $error = 'Database error occurred';
        }
    }

    $formData = $_POST;
} else {
    $formData = $director;
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="container">
    <h1>Edit Campus Director</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">

            <div class="form-group">
                <label>Campus</label>
                <input type="text" value="<?= htmlspecialchars(CAMPUSES[$director['campus']] ?? $director['campus']) ?>" disabled>
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

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Director</button>
                <a href="./directors.php" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>