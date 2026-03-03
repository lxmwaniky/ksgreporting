<?php
$user       = KSG\Auth::user();
$activePage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link rel="stylesheet" href="/ksg_reporting/public/assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="logo-bar">
            <img src="/ksg_reporting/public/assets/img/ksg-logo.png" alt="KSG Logo" height="60">
        </div>
        <?php if ($user): ?>
        <nav class="main-nav">
            <div class="nav-inner">
                <div class="nav-links">
                    <a href="/ksg_reporting/public/index.php" class="<?= $activePage === 'index' ? 'active' : '' ?>">Reports</a>
                    <a href="/ksg_reporting/public/submit.php" class="<?= $activePage === 'submit' ? 'active' : '' ?>">New Report</a>
                    <?php if (in_array($user['role'], ['admin', 'director', 'deputy_director', 'hod', 'staff'])): ?>
                        <a href="/ksg_reporting/public/tasks.php" class="<?= $activePage === 'tasks' ? 'active' : '' ?>">Tasks</a>
                    <?php endif; ?>
                    <?php if (in_array($user['role'], ['admin', 'director', 'deputy_director'])): ?>
                        <a href="/ksg_reporting/public/users.php" class="<?= $activePage === 'users' ? 'active' : '' ?>">Users</a>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="/ksg_reporting/public/directors.php" class="<?= $activePage === 'directors' ? 'active' : '' ?>">Directors</a>
                        <a href="/ksg_reporting/public/email_logs.php" class="<?= $activePage === 'email_logs' ? 'active' : '' ?>">Email Logs</a>
                    <?php endif; ?>
                </div>
                <div class="nav-user">
                    <span class="nav-username"><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="nav-badge"><?= htmlspecialchars(ROLES[$user['role']] ?? ucfirst($user['role']), ENT_QUOTES, 'UTF-8') ?></span>
                    <a href="/ksg_reporting/public/logout.php" class="btn-logout">Logout</a>
                </div>
            </div>
        </nav>
        <?php endif; ?>
    </header>
    <main class="main-content">