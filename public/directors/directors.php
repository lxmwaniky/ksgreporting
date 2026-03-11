<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/constants.php';

use KSG\Auth;
use KSG\Database;

Auth::startSession();
Auth::requireLogin();

if (Auth::user()['role'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Only administrators can manage directors.';
    header('Location: /index.php');
    exit;
}

$db      = Database::getInstance()->getPdo();
$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error']   ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$stmt = $db->query("
    SELECT cd.*, u.name AS user_name, u.email AS user_email, u.is_active AS user_status
    FROM campus_directors cd
    LEFT JOIN users u ON cd.user_id = u.id
    ORDER BY cd.campus
");
$directors = $stmt->fetchAll();

$pageTitle = 'Campus Directors';
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="page-header">
        <div class="page-header-content">
            <h1>Campus Directors</h1>
            <a href="/director_create.php" class="btn btn-primary">Add New Director</a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (empty($directors)): ?>
        <div class="empty-state">
            <p>No directors found. Add a director to get started.</p>
        </div>
    <?php else: ?>
    <div class="directors-grid">
        <?php foreach ($directors as $d):
            $hasAccount = !empty($d['user_id']);
            $isActive   = (bool)$d['is_active'];
            $initial    = strtoupper(mb_substr($d['director_name'], 0, 1));
        ?>
        <div class="director-card <?= !$isActive ? 'director-card--inactive' : '' ?>">

            <div class="director-card-top">
                <div class="director-avatar"><?= $initial ?></div>
                <div class="director-meta">
                    <div class="director-name">
                        <?= htmlspecialchars($d['director_name'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="director-campus">
                        <?= htmlspecialchars(CAMPUSES[$d['campus']] ?? $d['campus'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
                <span class="director-status-dot <?= $isActive ? 'dot--active' : 'dot--inactive' ?>"
                      title="<?= $isActive ? 'Active' : 'Inactive' ?>"></span>
            </div>

            <div class="director-card-body">
                <div class="director-detail-row">
                    <span class="director-detail-label">Email</span>
                    <span class="director-detail-value">
                        <?= htmlspecialchars($d['director_email'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
                <div class="director-detail-row">
                    <span class="director-detail-label">User Account</span>
                    <span class="director-detail-value">
                        <?php if ($hasAccount): ?>
                            <span class="account-linked">
                                &#10003; <?= htmlspecialchars($d['user_name'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php else: ?>
                            <span class="account-missing">&#9888; No account linked</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="director-detail-row">
                    <span class="director-detail-label">Status</span>
                    <span class="director-detail-value">
                        <span class="badge <?= $isActive ? 'badge-completed' : 'badge-cancelled' ?>">
                            <?= $isActive ? 'Active' : 'Inactive' ?>
                        </span>
                    </span>
                </div>
            </div>

            <div class="director-card-actions">
                <a href="/director_edit.php?id=<?= (int)$d['id'] ?>"
                   class="btn btn-sm btn-primary">Edit</a>
                <?php if ($isActive): ?>
                    <a href="/director_deactivate.php?id=<?= (int)$d['id'] ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Deactivate this director?')">Deactivate</a>
                <?php else: ?>
                    <a href="/director_activate.php?id=<?= (int)$d['id'] ?>"
                       class="btn btn-sm btn-secondary">Activate</a>
                <?php endif; ?>
            </div>

        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>