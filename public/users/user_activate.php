<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/constants.php';

use KSG\Auth;
use KSG\Database;

Auth::startSession();
Auth::requireLogin();

$currentUser = Auth::user();

if (!in_array($currentUser['role'], ['admin', 'director'])) {
    $_SESSION['error'] = 'Access denied. Only administrators and directors can activate users.';
    header('Location: /users.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid user ID';
    header('Location: /users.php');
    exit;
}

$userId = (int) $_GET['id'];

try {
    $db = Database::getInstance()->getPdo();
    
    $stmt = $db->prepare("SELECT id, name, campus, role, is_active FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error'] = 'User not found';
        header('Location: /users.php');
        exit;
    }
    
    if ($currentUser['role'] === 'director' && $user['campus'] !== $currentUser['campus']) {
        $_SESSION['error'] = 'You can only activate users in your campus';
        header('Location: /users.php');
        exit;
    }
    
    if ($user['is_active']) {
        $_SESSION['error'] = 'User is already active';
        header('Location: /users.php');
        exit;
    }
    
    $stmt = $db->prepare("UPDATE users SET is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    
    $_SESSION['success'] = "User {$user['name']} has been activated successfully";
    header('Location: /users.php');
    exit;
    
} catch (PDOException $e) {
    error_log('User activation error: ' . $e->getMessage());
    $_SESSION['error'] = 'Database error occurred';
    header('Location: /users.php');
    exit;
}