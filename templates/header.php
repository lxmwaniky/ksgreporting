<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script>
        window.KSG_STATUSES = <?= json_encode(STATUSES) ?>;
    </script>
</head>
<body>
    <?php if (KSG\Auth::isLoggedIn()): ?>
        <?php $user = KSG\Auth::currentUser(); ?>
        <nav>
            <div class="nav-brand">
                <strong><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="nav-links">
                <a href="/index.php">Dashboard</a>
                <a href="/submit.php">New Report</a>
                <?php if (in_array($user['role'], ['admin', 'hod'])): ?>
                    <a href="/users.php">User Management</a>
                <?php endif; ?>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="/email_logs.php">Email Logs</a>
                <?php endif; ?>
                <span class="nav-user">
                    <?= htmlspecialchars($user['name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?> 
                    (<?= htmlspecialchars(ucfirst($user['role'] ?? 'staff'), ENT_QUOTES, 'UTF-8') ?>)
                </span>
                <a href="/logout.php">Logout</a>
            </div>
        </nav>
    <?php endif; ?>