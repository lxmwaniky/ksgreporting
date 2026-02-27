<?php
$user = KSG\Auth::user();
$activePage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="logo-bar">
            <img src="/assets/img/ksg-logo.png" alt="KSG Logo" height="60">
        </div>
        <?php if ($user): ?>
        <nav class="main-nav">
            <div class="nav-inner">
                <div class="nav-links">
                    <a href="/index.php" class="<?= $activePage === 'index' ? 'active' : '' ?>">Reports</a>
                    <a href="/submit.php" class="<?= $activePage === 'submit' ? 'active' : '' ?>">New Report</a>
                    <?php if (in_array($user['role'], ['admin', 'director'])): ?>
                        <a href="/users.php" class="<?= $activePage === 'users' ? 'active' : '' ?>">Users</a>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="/directors.php" class="<?= $activePage === 'directors' ? 'active' : '' ?>">Directors</a>
                        <a href="/email_logs.php" class="<?= $activePage === 'email_logs' ? 'active' : '' ?>">Email Logs</a>
                    <?php endif; ?>
                </div>
                <div class="nav-user">
                    <span class="nav-username"><?= htmlspecialchars($user['name']) ?></span>
                    <span class="nav-badge"><?= ucfirst($user['role']) ?></span>
                    <a href="/logout.php" class="btn-logout">Logout</a>
                </div>
            </div>
        </nav>
        <?php endif; ?>
    </header>
    <main class="main-content">