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

try {
    $db = Database::getInstance()->getPdo();
    $stmt = $db->prepare("UPDATE campus_directors SET is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
    $stmt->execute([':id' => (int)$_GET['id']]);

    $_SESSION['success'] = 'Director activated successfully';
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error occurred';
}

header('Location: /directors.php');
exit;