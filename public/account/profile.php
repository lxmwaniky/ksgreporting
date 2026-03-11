<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/constants.php';

use KSG\Auth;
use KSG\Database;

Auth::startSession();
Auth::requireLogin();

$currentUser = Auth::user();
$db          = Database::getInstance()->getPdo();
$errors      = [];
$success     = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid security token.');
    }

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password']     ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword)) {
        $errors[] = 'Current password is required.';
    } elseif (empty($newPassword)) {
        $errors[] = 'New password is required.';
    } elseif (strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
        $errors[] = 'New password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $newPassword)) {
        $errors[] = 'New password must contain at least one number.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = 'New passwords do not match.';
    } else {
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmt->execute([':id' => $currentUser['id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt    = $db->prepare("UPDATE users SET password_hash = :hash, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([':hash' => $newHash, ':id' => $currentUser['id']]);
            $success = 'Password changed successfully.';
        }
    }
}

$pageTitle = 'My Profile';
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="page-header">
        <div class="page-header-content">
            <h1>My Profile</h1>
        </div>
    </div>

    <!-- Account Information -->
    <div class="card" style="margin-bottom:1.5rem;">
        <h2 class="profile-section-title">Account Information</h2>

        <!-- Avatar + name row -->
        <div class="profile-identity">
            <div class="profile-avatar">
                <?= strtoupper(mb_substr($currentUser['name'], 0, 1)) ?>
            </div>
            <div>
                <div class="profile-name"><?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?></div>
                <div style="margin-top:.3rem;">
                    <span class="badge badge-<?= htmlspecialchars($currentUser['role'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars(ROLES[$currentUser['role']] ?? ucfirst($currentUser['role']), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Info grid -->
        <div class="profile-info-grid">
            <div class="profile-info-item">
                <span class="profile-info-label">Email</span>
                <span class="profile-info-value"><?= htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="profile-info-item">
                <span class="profile-info-label">Campus</span>
                <span class="profile-info-value">
                    <?= htmlspecialchars(CAMPUSES[$currentUser['campus']] ?? $currentUser['campus'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
            <?php if (!empty($currentUser['department'])): ?>
            <div class="profile-info-item">
                <span class="profile-info-label">Department</span>
                <span class="profile-info-value">
                    <?= htmlspecialchars(DEPARTMENTS[$currentUser['department']] ?? $currentUser['department'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
            <?php endif; ?>
            <?php if (!empty($currentUser['designation'])): ?>
            <div class="profile-info-item">
                <span class="profile-info-label">Designation</span>
                <span class="profile-info-value">
                    <?= htmlspecialchars($currentUser['designation'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <p class="profile-admin-note">
            To update your name, department or other details, contact your administrator.
        </p>
    </div>

    <!-- Change Password -->
    <div class="card">
        <h2 class="profile-section-title">Change Password</h2>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin:0;padding-left:1.2rem;">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="POST" action="profile.php" style="max-width:460px;">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label for="current_password">Current Password <span class="required">*</span></label>
                <input type="password" id="current_password" name="current_password" required>
            </div>

            <div class="form-group">
                <label for="new_password">New Password <span class="required">*</span></label>
                <input type="password" id="new_password" name="new_password" required>
                <small>Min. 8 characters, one uppercase letter, one number</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<style>
.profile-section-title {
    font-size: .95rem;
    font-weight: 700;
    color: var(--ksg-brown);
    margin: 0 0 1.25rem;
    padding-bottom: .75rem;
    border-bottom: 1px solid var(--border);
}

/* Avatar + name */
.profile-identity {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.profile-avatar {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: var(--ksg-brown);
    color: #fff;
    font-size: 1.4rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.profile-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--ksg-dark);
}

/* Info grid — 2 columns on wider screens */
.profile-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .75rem 2rem;
    margin-bottom: 1.25rem;
}
@media (max-width: 560px) {
    .profile-info-grid { grid-template-columns: 1fr; }
}
.profile-info-item {
    display: flex;
    flex-direction: column;
    gap: .2rem;
    padding: .65rem .85rem;
    background: #f8f5ef;
    border-radius: var(--radius);
    border-left: 3px solid var(--ksg-brown);
}
.profile-info-label {
    font-size: .73rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #888;
}
.profile-info-value {
    font-size: .95rem;
    color: var(--ksg-dark);
    font-weight: 500;
}

.profile-admin-note {
    font-size: .8rem;
    color: #aaa;
    margin: 0;
}
</style>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>